<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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
            ->with(['venueCluster:id,name', 'booking:id,booking_code,booking_date,status', 'evidence'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return response()->json($complaints);
    }

    public function show(Request $request, string $id)
    {
        $complaint = Complaint::query()
            ->where('customer_id', $request->user()->id)
            ->with([
                'venueCluster:id,name',
                'booking:id,booking_code,booking_date,status,total_price',
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
        $data = $request->validate(['content' => ['required', 'string', 'min:2', 'max:4000']]);
        $complaint = Complaint::query()->where('customer_id', $request->user()->id)->findOrFail($id);

        if (in_array($complaint->status, ['resolved', 'rejected', 'closed'], true)) {
            return response()->json(['message' => 'Khiếu nại đã kết thúc, không thể gửi thêm phản hồi.'], 422);
        }

        $reply = ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        $reply->load('user:id,full_name,username');

        return response()->json([
            'message' => 'Đã gửi phản hồi.',
            'data' => [
                'type' => 'reply',
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => $reply->user,
                'created_at' => $reply->created_at,
            ],
        ], 201);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'complaint_type' => ['required', 'string', 'in:system,venue'],
            'venue_cluster_id' => ['required_if:complaint_type,venue', 'nullable', 'exists:venue_clusters,id'],
            'booking_id' => ['nullable', 'string', 'exists:bookings,id'],
            'content' => ['required', 'string', 'max:2000'],
            'evidence_images' => ['nullable', 'array', 'max:5'],
            'evidence_images.*' => ['image', 'max:5120'], // max 5MB per image
            'evidence_image' => ['nullable', 'image', 'max:5120'], // backward compatibility
        ]);

        $booking = null;
        if ($validated['complaint_type'] === 'venue' && ! empty($validated['booking_id'])) {
            $booking = Booking::query()->findOrFail($validated['booking_id']);
            if ((string) $booking->customer_id !== (string) $request->user()->id) {
                return response()->json([
                    'message' => 'Bạn chỉ có thể khiếu nại từ lịch đặt sân của chính mình.',
                ], 403);
            }

            if ((string) $booking->venue_cluster_id !== (string) $validated['venue_cluster_id']) {
                return response()->json([
                    'message' => 'Cụm sân không khớp với lịch đặt sân đã chọn.',
                ], 422);
            }
        }

        $complaint = Complaint::create([
            'complaint_type' => $validated['complaint_type'],
            'customer_id' => $request->user()->id,
            'venue_cluster_id' => $validated['complaint_type'] === 'venue' ? $validated['venue_cluster_id'] : null,
            'booking_id' => $validated['complaint_type'] === 'venue' ? $booking?->id : null,
            'content' => $validated['content'],
            'status' => 'open',
        ]);

        $imagesToProcess = [];
        if ($request->hasFile('evidence_images')) {
            $imagesToProcess = $request->file('evidence_images');
        } elseif ($request->hasFile('evidence_image')) {
            $imagesToProcess = [$request->file('evidence_image')];
        }

        if (! empty($imagesToProcess)) {
            if (! Storage::disk('public')->exists('complaints')) {
                Storage::disk('public')->makeDirectory('complaints');
            }

            $manager = ImageManager::usingDriver(new Driver());

            foreach (array_slice($imagesToProcess, 0, 5) as $index => $thumbnail) {
                if (! $thumbnail->isValid()) {
                    continue;
                }

                $image = $manager->decodePath($thumbnail->getPathname());
                $filename = uniqid('complaint_' . ($index + 1) . '_', true) . '.webp';
                $path = 'complaints/' . $filename;

                $image->save(storage_path('app/public/' . $path), 80);

                $complaint->evidence()->create([
                    'collection' => 'complaint_evidence',
                    'file_name' => $thumbnail->getClientOriginalName(),
                    'file_path' => Storage::url($path),
                    'mime_type' => 'image/webp',
                    'file_size' => filesize(storage_path('app/public/' . $path)),
                    'sort_order' => $index,
                ]);
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
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
            'data' => $complaint->load('evidence')
        ], 201);
    }
}
