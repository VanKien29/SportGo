<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PlayerPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerPostController extends Controller
{
    /**
     * Get public matchmaking posts
     */
    public function index(Request $request): JsonResponse
    {
        $posts = PlayerPost::with(['author', 'booking.venueCluster'])
            ->where('status', 'open')
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

        $data = $posts->map(function ($post) use ($participations) {
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
            ->whereIn('status', ['confirmed', 'paid'])
            ->where('booking_date', '>=', now()->toDateString())
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
            'booking_id' => ['required', 'string', 'exists:bookings,id'],
            'content' => ['nullable', 'string', 'max:2000'],
            'required_players' => ['required', 'integer', 'min:1'],
        ]);

        // Validate booking belongs to user
        $booking = Booking::with('venueCluster')->findOrFail($data['booking_id']);
        if ($booking->customer_id !== $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền tạo bài giao lưu cho lịch đặt này.',
            ], 403);
        }

        // Check if post already exists for this booking
        $existing = PlayerPost::where('booking_id', $booking->id)->first();
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lịch đặt sân này đã có bài giao lưu.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $post = PlayerPost::create([
                'booking_id' => $booking->id,
                'author_id' => $userId,
                'title' => 'Tìm người giao lưu',
                'description' => $data['content'] ?? '',
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
                'message' => 'Lỗi: ' . $e->getMessage(),
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

        if ($post->status !== 'open') {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài giao lưu này không còn nhận thêm người.',
            ], 400);
        }

        if ($post->author_id === $userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không thể tự tham gia bài giao lưu của chính mình.',
            ], 400);
        }

        $exists = DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn đã gửi yêu cầu tham gia bài này rồi.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            DB::table('player_post_participants')->insert([
                'post_id' => $post->id,
                'user_id' => $userId,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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

        if ($post->author_id !== $request->user()->id) {
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

        if ($post->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Check current status
            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if (!$participant || $participant->status === 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Yêu cầu không hợp lệ hoặc đã được duyệt.'], 400);
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
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject a participant
     */
    public function rejectParticipant(Request $request, $id, $userId): JsonResponse
    {
        $post = PlayerPost::findOrFail($id);

        if ($post->author_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if (!$participant || $participant->status === 'rejected') {
                DB::rollBack();
                return response()->json(['message' => 'Yêu cầu không hợp lệ hoặc đã bị từ chối.'], 400);
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
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}

