<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:open,processing,resolved,rejected,closed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $complaints = Complaint::query()
            ->where('customer_id', $request->user()->id)
            ->with([
                'venueCluster:id,name',
                'booking:id,booking_code,booking_date,start_time,end_time,status',
                'evidence',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return response()->json($complaints);
    }

    /**
     * Return only bookings that can currently be used for a venue complaint.
     * The same eligibility check is repeated in store() so the API never trusts
     * a booking selected from a stale client screen.
     */
    public function eligibleBookings(Request $request)
    {
        $now = Carbon::now($this->businessTimezone());
        $bookings = Booking::query()
            ->where('customer_id', $request->user()->id)
            ->whereIn('status', config('complaints.active_booking_statuses', ['confirmed', 'checked_in']))
            ->whereBetween('booking_date', [
                $now->copy()->subDay()->toDateString(),
                $now->copy()->addDay()->toDateString(),
            ])
            ->with([
                'venueCluster:id,name',
                'venueCourt:id,venue_cluster_id,name',
            ])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (Booking $booking): bool => $this->isBookingActive($booking, $now))
            ->map(fn (Booking $booking): array => $this->eligibleBookingPayload($booking, $now))
            ->values();

        return response()->json(['data' => $bookings]);
    }

    public function show(Request $request, string $id)
    {
        $complaint = Complaint::query()
            ->where('customer_id', $request->user()->id)
            ->with([
                'venueCluster:id,name',
                'booking:id,booking_code,booking_date,start_time,end_time,status,total_price',
                'evidence',
                'replies.user:id,full_name,username',
                'replies.evidence',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'complaint' => $complaint,
                'timeline' => $complaint->replies->map(fn (ComplaintReply $reply) => [
                    'type' => 'reply',
                    'id' => $reply->id,
                    'content' => $reply->content,
                    'user' => $reply->user,
                    'evidence' => $reply->evidence,
                    'created_at' => $reply->created_at,
                ])->values(),
            ],
        ]);
    }

    public function reply(Request $request, string $id)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'min:2', 'max:4000'],
            'evidence_images' => ['nullable', 'array', 'max:' . config('complaints.max_evidence_files', 5)],
            'evidence_images.*' => ['image', 'max:' . config('complaints.max_evidence_size_kb', 5120)],
        ]);

        $complaint = Complaint::query()
            ->where('customer_id', $request->user()->id)
            ->with(['evidence', 'replies.evidence'])
            ->findOrFail($id);

        if (in_array($complaint->status, ['resolved', 'rejected', 'closed'], true)) {
            return response()->json([
                'message' => 'Khiếu nại đã kết thúc, không thể gửi thêm phản hồi.',
                'code' => 'COMPLAINT_CLOSED',
            ], 422);
        }

        $files = $this->normaliseEvidenceFiles($request);
        if (blank($data['content'] ?? null) && $files === []) {
            throw ValidationException::withMessages([
                'content' => 'Vui lòng nhập nội dung hoặc đính kèm bằng chứng bổ sung.',
            ]);
        }

        $existingEvidenceCount = $complaint->evidence->count()
            + $complaint->replies->sum(fn (ComplaintReply $reply): int => $reply->evidence->count());
        if ($existingEvidenceCount + count($files) > (int) config('complaints.max_evidence_files', 5)) {
            return response()->json([
                'message' => 'Khiếu nại này đã đủ số lượng bằng chứng tối đa. Vui lòng thay thế hoặc chọn lại ảnh.',
                'code' => 'EVIDENCE_LIMIT_EXCEEDED',
            ], 422);
        }

        $this->ensureEvidenceSize($files);
        $reply = ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'content' => filled($data['content'] ?? null) ? $data['content'] : 'Bổ sung bằng chứng cho khiếu nại.',
        ]);
        $this->storeEvidenceFiles($reply, $files, 'complaint_reply_evidence');

        if ($complaint->status === 'open') {
            $complaint->forceFill(['status' => 'processing'])->save();
        }

        $reply->load(['user:id,full_name,username', 'evidence']);

        return response()->json([
            'message' => 'Đã gửi bổ sung cho khiếu nại.',
            'data' => [
                'type' => 'reply',
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => $reply->user,
                'evidence' => $reply->evidence,
                'created_at' => $reply->created_at,
            ],
        ], 201);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // New client submissions are service/venue complaints only. Existing
            // system complaints remain available to admins for historical data.
            'complaint_type' => ['required', 'string', 'in:venue'],
            'venue_cluster_id' => ['nullable', 'integer', 'exists:venue_clusters,id'],
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'content' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_images' => ['nullable', 'array', 'max:' . config('complaints.max_evidence_files', 5)],
            'evidence_images.*' => ['image', 'max:' . config('complaints.max_evidence_size_kb', 5120)],
            // Backward compatibility for the older single-image client.
            'evidence_image' => ['nullable', 'image', 'max:' . config('complaints.max_evidence_size_kb', 5120)],
        ]);

        $booking = Booking::query()
            ->with(['venueCluster:id,name', 'venueCourt:id,venue_cluster_id,name'])
            ->findOrFail($validated['booking_id']);

        if ((string) $booking->customer_id !== (string) $request->user()->id) {
            return response()->json([
                'message' => 'Bạn chỉ có thể khiếu nại từ lịch đặt sân của chính mình.',
                'code' => 'BOOKING_NOT_OWNED',
            ], 403);
        }

        if (! empty($validated['venue_cluster_id'])
            && (string) $booking->venue_cluster_id !== (string) $validated['venue_cluster_id']) {
            return response()->json([
                'message' => 'Cụm sân không khớp với lịch đặt sân đã chọn.',
                'code' => 'BOOKING_VENUE_MISMATCH',
            ], 422);
        }

        $now = Carbon::now($this->businessTimezone());
        if (! $this->isBookingActive($booking, $now)) {
            return response()->json([
                'message' => 'Chỉ có thể tạo khiếu nại trong thời gian booking đang hoạt động tại sân.',
                'code' => 'BOOKING_NOT_ACTIVE',
            ], 422);
        }

        $idempotencyKey = Str::limit(trim((string) $request->header('Idempotency-Key')), 100, '');
        if ($idempotencyKey !== '') {
            $existingByKey = Complaint::query()
                ->where('customer_id', $request->user()->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existingByKey) {
                return response()->json([
                    'message' => 'Yêu cầu đã được tiếp nhận trước đó.',
                    'data' => $existingByKey->load('evidence'),
                    'idempotent_replay' => true,
                ]);
            }
        }

        $duplicate = Complaint::query()
            ->where('customer_id', $request->user()->id)
            ->where('complaint_type', 'venue')
            ->where('booking_id', $booking->id)
            ->where('created_at', '>=', now()->subHours((int) config('complaints.duplicate_window_hours', 24)))
            ->latest()
            ->first();
        if ($duplicate) {
            return response()->json([
                'message' => 'Booking này đã có khiếu nại trong 24 giờ qua. Bạn có thể mở khiếu nại hiện tại để bổ sung nội dung hoặc bằng chứng.',
                'code' => 'DUPLICATE_COMPLAINT',
                'existing_complaint_id' => $duplicate->id,
                'data' => $duplicate->only(['id', 'status', 'created_at']),
            ], 409);
        }

        $files = $this->normaliseEvidenceFiles($request);
        $this->ensureEvidenceSize($files);

        $bookingSnapshot = [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'status' => $booking->status,
            'booking_date' => $booking->booking_date?->toDateString() ?? (string) $booking->booking_date,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'venue_cluster_id' => $booking->venue_cluster_id,
            'venue_cluster_name' => $booking->venueCluster?->name,
            'venue_court_id' => $booking->venue_court_id,
            'venue_court_name' => $booking->venueCourt?->name,
            'captured_at' => $now->toIso8601String(),
        ];
        $fingerprint = hash('sha256', implode('|', [
            $request->user()->id,
            $booking->id,
            Str::lower(preg_replace('/\s+/', ' ', trim($validated['content']))),
        ]));

        $complaint = Complaint::create([
            'complaint_type' => 'venue',
            'customer_id' => $request->user()->id,
            'venue_cluster_id' => $booking->venue_cluster_id,
            'booking_id' => $booking->id,
            'content' => $validated['content'],
            'status' => 'open',
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'request_fingerprint' => $fingerprint,
            'booking_snapshot' => $bookingSnapshot,
            'submitted_ip' => $request->ip(),
            'submitted_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'policy_version' => config('complaints.policy_version'),
            'response_due_at' => now()->addHours((int) config('complaints.first_response_due_hours', 24)),
            'resolution_due_at' => now()->addDays((int) config('complaints.resolution_due_days', 3)),
        ]);

        $this->storeEvidenceFiles($complaint, $files, 'complaint_evidence');

        if (Schema::hasTable('notifications')) {
            \App\Models\Notification::query()->create([
                'user_id' => $request->user()->id,
                'type' => 'complaint_created',
                'title' => 'Gửi khiếu nại thành công',
                'body' => 'Chúng tôi đã ghi nhận yêu cầu khiếu nại của bạn và sẽ xử lý trong thời gian sớm nhất.',
                'reference_type' => Complaint::class,
                'reference_id' => $complaint->id,
                'data' => ['status' => 'open'],
                'is_read' => false,
            ]);
        }

        return response()->json([
            'message' => 'Khiếu nại của bạn đã được gửi thành công.',
            'data' => $complaint->load('evidence'),
        ], 201);
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', config('app.timezone', 'UTC'));
    }

    private function bookingWindow(Booking $booking): ?array
    {
        if (! $booking->booking_date || ! $booking->start_time || ! $booking->end_time) {
            return null;
        }

        $date = $booking->booking_date instanceof CarbonInterface
            ? $booking->booking_date->toDateString()
            : (string) $booking->booking_date;
        $timezone = $this->businessTimezone();
        $startTime = substr((string) $booking->start_time, 0, 8);
        $endTime = substr((string) $booking->end_time, 0, 8);
        $start = Carbon::parse($date . ' ' . $startTime, $timezone);
        $end = $endTime === '24:00:00'
            ? Carbon::parse($date . ' 00:00:00', $timezone)->addDay()
            : Carbon::parse($date . ' ' . $endTime, $timezone);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [
            'start' => $start,
            'end' => $end,
            'active_start' => $start->copy()->subMinutes((int) config('complaints.active_window_before_minutes', 15)),
            'active_end' => $end->copy()->addMinutes((int) config('complaints.active_window_after_minutes', 60)),
        ];
    }

    private function isBookingActive(Booking $booking, CarbonInterface $now): bool
    {
        if (! in_array($booking->status, config('complaints.active_booking_statuses', ['confirmed', 'checked_in']), true)) {
            return false;
        }

        $window = $this->bookingWindow($booking);
        return $window !== null
            && $now->greaterThanOrEqualTo($window['active_start'])
            && $now->lessThanOrEqualTo($window['active_end']);
    }

    private function eligibleBookingPayload(Booking $booking, CarbonInterface $now): array
    {
        $window = $this->bookingWindow($booking);

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_date' => $booking->booking_date?->toDateString() ?? (string) $booking->booking_date,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'status' => $booking->status,
            'venue_cluster_id' => $booking->venue_cluster_id,
            'venue_cluster' => $booking->venueCluster?->only(['id', 'name']),
            'venue_court' => $booking->venueCourt?->only(['id', 'name']),
            'active_window_start' => $window['active_start']->toIso8601String(),
            'active_window_end' => $window['active_end']->toIso8601String(),
            'minutes_remaining' => max(0, $now->diffInMinutes($window['active_end'], false)),
        ];
    }

    /** @return array<int, \Illuminate\Http\UploadedFile> */
    private function normaliseEvidenceFiles(Request $request): array
    {
        $files = $request->file('evidence_images', []);
        $files = is_array($files) ? array_values(array_filter($files)) : ($files ? [$files] : []);

        if ($files === [] && $request->hasFile('evidence_image')) {
            $files = [$request->file('evidence_image')];
        }

        return array_slice($files, 0, (int) config('complaints.max_evidence_files', 5));
    }

    /** @param array<int, \Illuminate\Http\UploadedFile> $files */
    private function ensureEvidenceSize(array $files): void
    {
        $maxTotalBytes = (int) config('complaints.max_evidence_total_size_kb', 20480) * 1024;
        $totalBytes = array_sum(array_map(fn ($file): int => (int) $file->getSize(), $files));
        if ($totalBytes > $maxTotalBytes) {
            throw ValidationException::withMessages([
                'evidence_images' => 'Tổng dung lượng ảnh minh chứng không được vượt quá 20 MB.',
            ]);
        }
    }

    /** @param array<int, \Illuminate\Http\UploadedFile> $files */
    private function storeEvidenceFiles(Model $owner, array $files, string $collection): void
    {
        if ($files === []) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('complaints');
        $manager = ImageManager::usingDriver(new Driver());

        foreach ($files as $index => $file) {
            if (! $file->isValid()) {
                continue;
            }

            $image = $manager->decodePath($file->getPathname());
            $filename = Str::uuid()->toString() . '.webp';
            $path = 'complaints/' . $filename;
            $image->save(storage_path('app/public/' . $path), 80);

            $owner->evidence()->create([
                'collection' => $collection,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => Storage::url($path),
                'mime_type' => 'image/webp',
                'file_size' => filesize(storage_path('app/public/' . $path)),
                'sort_order' => $index,
            ]);
        }
    }
}
