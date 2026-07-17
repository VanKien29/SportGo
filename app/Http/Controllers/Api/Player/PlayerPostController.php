<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PlayerPost;
use App\Services\CommunityAuthorBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerPostController extends Controller
{
    public function __construct(private CommunityAuthorBadgeService $authorBadges) {}

    /**
     * Get public matchmaking posts
     */
    public function index(Request $request): JsonResponse
    {
        $posts = PlayerPost::with(['author', 'booking.venueCluster'])
            ->where('status', 'open')
            ->whereHas('booking', function ($q) {
                $q->where('status', 'confirmed')
                    ->where(function ($query) {
                        $query->where('booking_date', '>', now()->toDateString())
                            ->orWhere(function ($sub) {
                                $sub->where('booking_date', '=', now()->toDateString())
                                    ->where('end_time', '>', now()->toTimeString());
                            });
                    });
            })
            ->when($request->author_id, fn ($query) => $query->where('author_id', $request->author_id))
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $userId = auth('sanctum')->id();
        $postIds = $posts->pluck('id')->toArray();
        $participations = [];

        if ($userId && !empty($postIds)) {
            $participations = DB::table('player_post_participants')
                ->whereIn('post_id', $postIds)
                ->where('user_id', $userId)
                ->pluck('status', 'post_id')
                ->toArray();
        }

        $authorBadges = $this->authorBadges->lookup($posts->pluck('author_id'));
        $data = $posts->map(function ($post) use ($participations, $authorBadges) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'needed_players' => $post->needed_players,
                'status' => $post->status,
                'created_at' => $post->created_at,
                'user_status' => $participations[$post->id] ?? null,
                'author' => [
                    'id' => $post->author->id,
                    'name' => $post->author->full_name ?? $post->author->username ?? 'Người dùng',
                    'avatar' => $post->author->avatar_url ?? null,
                    'author_badges' => $authorBadges[(string) $post->author_id] ?? [],
                ],
                'booking' => [
                    'date' => $post->booking->booking_date->format('Y-m-d'),
                    'time' => substr($post->booking->start_time, 0, 5),
                    'venue_name' => $post->booking->venueCluster->name ?? 'Sân chưa xác định',
                    'venue_address' => $post->booking->venueCluster->address ?? '',
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
        ]);
    }

    /**
     * Get eligible bookings for creating a matchmaking post.
     */
    public function eligibleBookings(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Fetch bookings that are in the future and confirmed/paid
        $bookings = Booking::with(['venueCluster'])
            ->where('customer_id', $userId)
            ->where('status', 'confirmed')
            ->where(function ($query) {
                $query->whereDate('booking_date', '>', now()->toDateString())
                    ->orWhere(function ($todayQuery) {
                        $todayQuery->whereDate('booking_date', now()->toDateString())
                            ->where('end_time', '>', now()->toTimeString());
                    });
            })
            ->whereNotIn('id', PlayerPost::query()->select('booking_id'))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        $eligible = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'venue_id' => $booking->venue_cluster_id,
                'venue_name' => $booking->venueCluster->name ?? 'Unknown',
                'location' => $booking->venueCluster->address ?? null,
                'date' => $booking->booking_date->format('Y-m-d'),
                'time' => substr($booking->start_time, 0, 5),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $eligible,
        ]);
    }

    /**
     * Store a new matchmaking post.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'content' => ['nullable', 'string', 'min:10', 'max:2000'],
            'required_players' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        DB::beginTransaction();
        try {
            // Lock the booking so two concurrent requests cannot create duplicate posts.
            $booking = Booking::with('venueCluster')
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $booking->customer_id !== (string) $userId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền tạo bài giao lưu cho lịch đặt này.',
                ], 403);
            }

            if (! $this->isEligibleBooking($booking)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Chỉ có thể tạo bài giao lưu cho booking sắp tới đã được xác nhận.',
                ], 409);
            }

            if (PlayerPost::where('booking_id', $booking->id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lịch đặt sân này đã có bài giao lưu.',
                ], 409);
            }

            $post = PlayerPost::create([
                'booking_id' => $booking->id,
                'author_id' => $userId,
                'title' => 'Tìm người giao lưu',
                'description' => isset($data['content']) ? trim(strip_tags($data['content'])) : '',
                'needed_players' => $data['required_players'],
                'cost_per_player' => 0,
                'status' => 'open', // Trạng thái mở để tuyển người
            ]);

            // Notify user
            Notification::create([
                'user_id' => $userId,
                'type' => 'matchmaking_post_created',
                'title' => 'Đăng bài giao lưu thành công',
                'body' => 'Bài giao lưu của bạn cho lịch đặt sân ' . ($booking->venueCluster->name ?? '') . ' đã được đăng lên Cộng đồng.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng bài thành công.',
                'data' => $post,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('PlayerPost Create Error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo bài giao lưu. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Join a matchmaking post.
     */
    public function join(Request $request, $id): JsonResponse
    {
        $userId = $request->user()->id;
        $userFullName = $request->user()->full_name ?? $request->user()->username ?? 'Một người dùng';

        $post = PlayerPost::with('booking.venueCluster')->findOrFail($id);

        if ($post->status !== 'open' || $post->needed_players <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài giao lưu này không còn nhận thêm người.',
            ], 409);
        }

        if (! $post->booking || ! $this->isEligibleBooking($post->booking)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Buổi giao lưu này đã diễn ra hoặc không còn hợp lệ.',
            ], 409);
        }

        if ((string) $post->author_id === (string) $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể tự tham gia bài giao lưu của chính mình.',
            ], 409);
        }

        $exists = DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đã gửi yêu cầu tham gia bài này rồi.',
            ], 409);
        }

        DB::beginTransaction();
        try {
            $post = PlayerPost::with('booking.venueCluster')
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($post->status !== 'open' || $post->needed_players <= 0 || ! $post->booking || ! $this->isEligibleBooking($post->booking)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bài giao lưu này không còn nhận thêm người.',
                ], 409);
            }

            $inserted = DB::table('player_post_participants')->insertOrIgnore([
                'post_id' => $post->id,
                'user_id' => $userId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn đã gửi yêu cầu tham gia bài này rồi.',
                ], 409);
            }

            // Notify the author
            $venueName = $post->booking->venueCluster->name ?? 'sân';
            Notification::create([
                'user_id' => $post->author_id,
                'type' => 'matchmaking_join_request',
                'title' => 'Có người muốn ghép kèo',
                'body' => "{$userFullName} vừa xin tham gia bài giao lưu của bạn tại {$venueName}.",
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã gửi yêu cầu tham gia thành công.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('PlayerPost Join Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Đã có lỗi xảy ra. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Get participants for a matchmaking post
     */
    public function participants(Request $request, $id): JsonResponse
    {
        $post = PlayerPost::with('booking.venueCluster')->findOrFail($id);

        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $participants = DB::table('player_post_participants')
            ->join('users', 'player_post_participants.user_id', '=', 'users.id')
            ->where('post_id', $post->id)
            ->select(
                'player_post_participants.user_id',
                'player_post_participants.status',
                'player_post_participants.created_at',
                'users.full_name',
                'users.username',
                'users.avatar_url'
            )
            ->orderBy('player_post_participants.created_at', 'desc')
            ->get();

        $data = $participants->map(function ($p) {
            return [
                'user_id' => $p->user_id,
                'name' => $p->full_name ?? $p->username ?? 'Người dùng',
                'avatar' => $p->avatar_url ?? null,
                'status' => $p->status,
                'created_at' => $p->created_at,
            ];
        });

        return response()->json([
            'post' => [
                'id' => $post->id,
                'status' => $post->status,
                'needed_players' => $post->needed_players,
                'venue_name' => $post->booking->venueCluster->name ?? 'Sân chưa xác định',
                'time' => substr($post->booking->start_time, 0, 5) . ' - ' . $post->booking->booking_date->format('d/m/Y'),
            ],
            'participants' => $data
        ]);
    }

    /**
     * Approve a participant
     */
    public function approveParticipant(Request $request, $id, $userId): JsonResponse
    {
        $post = PlayerPost::findOrFail($id);

        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $post = PlayerPost::with('booking')->whereKey($id)->lockForUpdate()->firstOrFail();

            // Check current status
            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if (!$participant || $participant->status === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Yêu cầu không hợp lệ hoặc đã được duyệt.'], 409);
            }

            if ($post->status !== 'open' || $post->needed_players <= 0 || ! $post->booking || ! $this->isEligibleBooking($post->booking)) {
                DB::rollBack();
                return response()->json(['message' => 'Bài giao lưu đã đủ người hoặc không còn hiệu lực.'], 409);
            }

            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->update(['status' => 'approved', 'responded_at' => now()]);

            // Decrease needed_players
            if ($post->needed_players > 0) {
                $post->needed_players -= 1;
                if ($post->needed_players <= 0) {
                    $post->status = 'full';
                }
                $post->save();
            }

            // Notify user
            Notification::create([
                'user_id' => $userId,
                'type' => 'matchmaking_join_approved',
                'title' => 'Yêu cầu ghép kèo được chấp nhận',
                'body' => 'Chủ bài viết đã đồng ý cho bạn tham gia ghép kèo tại sân ' . ($post->booking->venueCluster->name ?? 'sân') . '.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
            ]);

            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Không thể duyệt người tham gia. Vui lòng thử lại.'], 500);
        }
    }

    /**
     * Reject a participant
     */
    public function rejectParticipant(Request $request, $id, $userId): JsonResponse
    {
        $post = PlayerPost::findOrFail($id);

        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $post = PlayerPost::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if (!$participant || $participant->status === 'rejected') {
                DB::rollBack();
                return response()->json(['message' => 'Yêu cầu không hợp lệ hoặc đã bị từ chối.'], 409);
            }

            $wasApproved = $participant->status === 'approved';

            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->update(['status' => 'rejected', 'responded_at' => now()]);

            // If it was approved before, restore needed_players
            if ($wasApproved) {
                $post->needed_players += 1;
                if ($post->status === 'full' && $post->needed_players > 0) {
                    $post->status = 'open';
                }
                $post->save();
            }

            // Notify user
            Notification::create([
                'user_id' => $userId,
                'type' => 'matchmaking_join_rejected',
                'title' => 'Yêu cầu ghép kèo bị từ chối',
                'body' => 'Rất tiếc, chủ bài viết đã từ chối yêu cầu ghép kèo của bạn.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
            ]);

            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Không thể từ chối yêu cầu. Vui lòng thử lại.'], 500);
        }
    }

    private function isEligibleBooking(Booking $booking): bool
    {
        return $booking->status === 'confirmed' && $this->isFutureBooking($booking);
    }

    private function isFutureBooking(Booking $booking): bool
    {
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;
        if ($date > now()->toDateString()) {
            return true;
        }

        return $date === now()->toDateString()
            && substr((string) $booking->end_time, 0, 8) > now()->toTimeString();
    }
}
