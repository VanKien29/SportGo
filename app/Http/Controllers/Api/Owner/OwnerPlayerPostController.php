<?php
 
namespace App\Http\Controllers\Api\Owner;
 
use App\Http\Controllers\Controller;
use App\Models\PlayerPost;
use App\Models\VenueCluster;
use App\Models\Report;
use App\Services\MatchmakingChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
 
class OwnerPlayerPostController extends Controller
{
    public function __construct(private MatchmakingChatService $matchmakingChat) {}

    /**
     * Get owned or assigned cluster IDs for the authenticated user.
     */
    private function getOwnerClusterIds(Request $request): \Illuminate\Support\Collection
    {
        $ownedClusterIds = VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->pluck('id');
 
        $assignedClusterIds = DB::table('venue_staff_assignments')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');
 
        return $ownedClusterIds->merge($assignedClusterIds)->unique()->values();
    }
 
    /**
     * Display a listing of the player matchmaking posts.
     */
    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);
 
        $posts = PlayerPost::query()
            ->whereHas('booking', function ($query) use ($clusterIds) {
                $query->whereIn('venue_cluster_id', $clusterIds);
            })
            ->with([
                'author:id,username,full_name,email,phone,avatar_url',
                'booking:id,booking_code,booking_date,start_time,end_time,venue_cluster_id,venue_court_id',
                'booking.venueCluster:id,name',
                'booking.venueCourt:id,name,court_type_id',
                'booking.venueCourt.courtType:id,name',
            ])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('venue_cluster_id'), function ($query) use ($request, $clusterIds) {
                if ($clusterIds->contains($request->query('venue_cluster_id'))) {
                    $query->whereHas('booking', function ($q) use ($request) {
                        $q->where('venue_cluster_id', $request->query('venue_cluster_id'));
                    });
                }
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . $request->query('search') . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                      ->orWhere('description', 'like', $search)
                      ->orWhereHas('author', function ($aq) use ($search) {
                          $aq->where('username', 'like', $search)
                             ->orWhere('full_name', 'like', $search);
                      });
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));

        $groupChatIds = DB::table('conversations')
            ->where('type', 'player_post')
            ->where('reference_type', 'player_post')
            ->whereIn('reference_id', $posts->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->pluck('id', 'reference_id');
        $posts->getCollection()->transform(function (PlayerPost $post) use ($groupChatIds): PlayerPost {
            $post->setAttribute('group_chat_id', $groupChatIds[(string) $post->id] ?? null);
            return $post;
        });

        return response()->json([
            'status' => 'success',
            'data' => $posts,
        ]);
    }

    /**
     * Store a new matchmaking post by owner.
     */
    public function store(Request $request): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $data = $request->validate([
            'booking_id' => ['required', 'string', 'exists:bookings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'needed_players' => ['required', 'integer', 'min:1'],
            'cost_per_player' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5MB
        ]);

        // Validate booking belongs to owner's cluster
        $booking = \App\Models\Booking::findOrFail($data['booking_id']);
        if (!$clusterIds->contains($booking->venue_cluster_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lịch đặt sân không thuộc quyền quản lý của bạn.',
            ], 403);
        }

        if ($booking->status !== 'confirmed' || ! $this->isBeforeBookingStart($booking)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ có thể tạo bài giao lưu cho booking đã xác nhận và chưa bắt đầu.',
            ], 409);
        }

        // A booking can have one matchmaking post for its full audit trail,
        // including after the session has been closed.
        $existingPost = PlayerPost::where('booking_id', $booking->id)->first();

        if ($existingPost) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lịch đặt sân này đã có bài giao lưu.',
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('matchmaking-posts', 'public');
        }

        $post = PlayerPost::create([
            'booking_id' => $booking->id,
            'author_id' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'needed_players' => $data['needed_players'],
            'cost_per_player' => $data['cost_per_player'] ?? 0,
            'status' => 'open',
        ]);

        // Every matchmaking post owns one persistent private group. Approved
        // participants are added to it by the player post lifecycle.
        $group = $this->matchmakingChat->ensureGroup($post->load('booking.venueCluster'));

        // Trả về post kèm relation để append vào list trên UI
        $post->load([
            'author:id,username,full_name,email,phone,avatar_url',
            'booking:id,booking_code,booking_date,start_time,end_time,venue_cluster_id,venue_court_id',
            'booking.venueCluster:id,name',
            'booking.venueCourt:id,name',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đăng bài giao lưu thành công.',
            'data' => array_merge($post->toArray(), ['group_chat_id' => $group->id]),
        ]);
    }
 
    /**
     * Hide (close) a matchmaking post by owner.
     */
    public function hide(Request $request, string $id): JsonResponse
    {
        $post = PlayerPost::query()->with('booking')->findOrFail($id);
        $clusterIds = $this->getOwnerClusterIds($request);
 
        if (!$clusterIds->contains($post->booking->venue_cluster_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền ẩn bài đăng này.',
            ], 403);
        }
 
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do ẩn bài viết.',
        ]);
 
        $post->status = 'closed';
        $post->status_reason = 'Ẩn bởi chủ sân. Lý do: ' . $request->input('reason');
        $post->save();
 
        return response()->json([
            'status' => 'success',
            'message' => 'Đã ẩn bài giao lưu thành công.',
            'data' => $post,
        ]);
    }

    /**
     * Get eligible bookings for creating a new matchmaking post.
     */
    public function eligibleBookings(Request $request): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);
        $venueClusterId = $request->query('venue_cluster_id');

        if ($venueClusterId && !$clusterIds->contains($venueClusterId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cụm sân không thuộc quyền quản lý của bạn.',
            ], 403);
        }

        $queryClusterIds = $venueClusterId ? [$venueClusterId] : $clusterIds;

        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $today = $businessNow->toDateString();
        $time = $businessNow->toTimeString();
        $bookings = \App\Models\Booking::query()
            ->whereIn('venue_cluster_id', $queryClusterIds)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($today, $time) {
                $query->where('booking_date', '>', $today)
                    ->orWhere(function ($sub) use ($today, $time) {
                        $sub->where('booking_date', $today)->where('start_time', '>', $time);
                    });
            })
            ->whereNotIn('id', \App\Models\PlayerPost::pluck('booking_id'))
            ->with(['venueCourt:id,name,court_type_id', 'venueCourt.courtType:id,name'])
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->limit(50) // Giới hạn 50 lịch sắp tới để chọn cho dễ
            ->get(['id', 'booking_code', 'booking_date', 'start_time', 'end_time', 'venue_cluster_id', 'venue_court_id']);

        return response()->json([
            'status' => 'success',
            'data' => $bookings,
        ]);
    }

    private function isBeforeBookingStart(\App\Models\Booking $booking): bool
    {
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;

        return $date > $businessNow->toDateString()
            || ($date === $businessNow->toDateString() && substr((string) $booking->start_time, 0, 8) > $businessNow->toTimeString());
    }
 
    /**
     * Report a matchmaking post by owner.
     */
    public function report(Request $request, string $id): JsonResponse
    {
        $post = PlayerPost::query()->with('booking')->findOrFail($id);
        $clusterIds = $this->getOwnerClusterIds($request);
 
        if (!$clusterIds->contains($post->booking->venue_cluster_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền báo cáo bài đăng này.',
            ], 403);
        }
 
        $request->validate([
            'reason' => ['required', 'string', 'in:spam,offensive,fake,harassment,other'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Vui lòng chọn loại vi phạm.',
        ]);
 
        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => PlayerPost::class,
            'reportable_id' => $post->id,
            'reason' => $request->input('reason'),
            'description' => $request->input('description'),
            'status' => 'pending',
        ]);
 
        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi báo cáo vi phạm thành công.',
            'data' => $report,
        ]);
    }
}
