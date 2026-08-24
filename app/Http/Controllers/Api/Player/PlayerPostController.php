<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PlayerPost;
use App\Services\CommunityAuthorBadgeService;
use App\Services\MatchmakingChatService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlayerPostController extends Controller
{
    public function __construct(
        private CommunityAuthorBadgeService $authorBadges,
        private MatchmakingChatService $matchmakingChat,
    ) {}

    /**
     * Get public matchmaking posts
     */
    public function index(Request $request): JsonResponse
    {
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $posts = PlayerPost::with([
                'author:id,full_name,username,avatar_url',
                'booking.venueCluster:id,name,address',
            ])
            ->withCount([
                'participants as approved_players_count' => fn ($query) => $query
                    ->where('player_post_participants.status', 'approved'),
            ])
            ->where('status', 'open')
            ->whereHas('booking', function ($q) use ($businessNow) {
                $q->where('status', 'confirmed')
                    ->where(function ($query) use ($businessNow) {
                        $today = $businessNow->toDateString();
                        $query->where('booking_date', '>', $today)
                            ->orWhere(function ($sub) use ($businessNow) {
                                $sub->where('booking_date', '=', $businessNow->toDateString())
                                    ->where('start_time', '>', $businessNow->toTimeString());
                            });
                    });
            })
            ->when($request->author_id, fn ($query) => $query->where('author_id', $request->author_id))
            ->orderBy('created_at', 'desc')
            ->paginate((int) min(max((int) $request->input('per_page', 10), 1), 50));

        $userId = auth('sanctum')->id();
        $postIds = $posts->pluck('id')->toArray();
        $participations = [];
        $groupChatIds = [];

        if ($userId && !empty($postIds)) {
            $participations = DB::table('player_post_participants')
                ->whereIn('post_id', $postIds)
                ->where('user_id', $userId)
                ->pluck('status', 'post_id')
                ->toArray();
        }
        if (! empty($postIds)) {
            $groupChatIds = DB::table('conversations')
                ->where('type', 'player_post')
                ->where('reference_type', 'player_post')
                ->whereIn('reference_id', array_map('strval', $postIds))
                ->pluck('id', 'reference_id')
                ->toArray();
        }

        $authorBadges = $this->authorBadges->lookup($posts->pluck('author_id'));
        $data = $posts->map(function ($post) use ($participations, $authorBadges, $groupChatIds) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'needed_players' => $post->needed_players,
                'approved_players' => (int) $post->approved_players_count,
                'total_players' => (int) $post->approved_players_count + (int) $post->needed_players,
                'status' => $post->status,
                'created_at' => $post->created_at,
                'user_status' => $participations[$post->id] ?? null,
                'group_chat_id' => $groupChatIds[(string) $post->id] ?? null,
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
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $today = $businessNow->toDateString();
        $time = $businessNow->toTimeString();
        $bookings = Booking::query()
            ->select(['id', 'venue_cluster_id', 'booking_date', 'start_time'])
            ->with(['venueCluster:id,name,address'])
            ->where('customer_id', $userId)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($today, $time) {
                $query->where('booking_date', '>', $today)
                    ->orWhere(function ($todayQuery) use ($today, $time) {
                        $todayQuery->where('booking_date', '=', $today)
                            ->where('start_time', '>', $time);
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
     * Participation history for the authenticated user. This is intentionally
     * separate from the public feed so ended posts remain visible privately.
     */
    public function myRequests(Request $request): JsonResponse
    {
        // Reconcile the boundary on read as well as on join/approve so pending
        // requests cannot remain actionable after the booking has started.
        PlayerPost::query()
            ->with('booking')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->get()
            ->each(fn (PlayerPost $post) => $this->synchronizeLifecycle($post));

        $query = DB::table('player_post_participants as participant')
            ->join('player_posts as post', 'post.id', '=', 'participant.post_id')
            ->join('bookings as booking', 'booking.id', '=', 'post.booking_id')
            ->join('venue_clusters as venue', 'venue.id', '=', 'booking.venue_cluster_id')
            ->join('users as author', 'author.id', '=', 'post.author_id')
            ->where('participant.user_id', $request->user()->id)
            ->select([
                'participant.id', 'participant.post_id', 'participant.status', 'participant.message',
                'participant.created_at', 'participant.responded_at', 'participant.left_at',
                'post.title', 'post.description', 'post.status as post_status',
                'post.needed_players', 'booking.booking_date', 'booking.start_time', 'booking.end_time',
                'venue.name as venue_name', 'venue.address as venue_address',
                'author.id as author_id', 'author.full_name as author_name', 'author.username as author_username',
                'author.avatar_url as author_avatar',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('participant.status', $request->string('status')->toString()))
            ->orderByDesc('participant.created_at');

        $items = $query->paginate(min(max((int) $request->input('per_page', 15), 5), 50));
        $data = collect($items->items())->map(fn ($item) => $this->participantPayload($item))->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'total' => $items->total(),
        ]);
    }

    public function myRequest(Request $request, int $id): JsonResponse
    {
        $postId = DB::table('player_post_participants')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->value('post_id');
        if ($postId) {
            $post = PlayerPost::with('booking')->find($postId);
            if ($post) $this->synchronizeLifecycle($post);
        }

        $item = DB::table('player_post_participants as participant')
            ->join('player_posts as post', 'post.id', '=', 'participant.post_id')
            ->join('bookings as booking', 'booking.id', '=', 'post.booking_id')
            ->join('venue_clusters as venue', 'venue.id', '=', 'booking.venue_cluster_id')
            ->join('users as author', 'author.id', '=', 'post.author_id')
            ->where('participant.id', $id)
            ->where('participant.user_id', $request->user()->id)
            ->select([
                'participant.id', 'participant.post_id', 'participant.status', 'participant.message',
                'participant.created_at', 'participant.responded_at', 'participant.left_at',
                'post.title', 'post.description', 'post.status as post_status',
                'booking.booking_date', 'booking.start_time', 'booking.end_time',
                'venue.name as venue_name', 'venue.address as venue_address',
                'author.id as author_id', 'author.full_name as author_name', 'author.username as author_username',
                'author.avatar_url as author_avatar',
            ])
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $this->participantPayload($item)]);
    }

    private function participantPayload(object $item): array
    {
        return [
            'id' => $item->id,
            'post_id' => $item->post_id,
            'status' => $item->status,
            'message' => $item->message,
            'created_at' => $item->created_at,
            'responded_at' => $item->responded_at,
            'left_at' => $item->left_at,
            'group_chat_id' => $this->matchmakingChat->conversationFor((int) $item->post_id)?->id,
            'post' => [
                'title' => $item->title,
                'description' => $item->description,
                'status' => $item->post_status,
            ],
            'booking' => [
                'date' => $item->booking_date,
                'start_time' => substr((string) $item->start_time, 0, 5),
                'end_time' => substr((string) $item->end_time, 0, 5),
                'venue_name' => $item->venue_name,
                'venue_address' => $item->venue_address,
            ],
            'author' => [
                'id' => $item->author_id,
                'name' => $item->author_name ?: $item->author_username ?: 'Người dùng',
                'avatar' => $item->author_avatar,
            ],
            'can_leave' => in_array($item->status, ['pending', 'approved'], true),
        ];
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
            // Do not lock the booking row here. Booking creation/status updates can hold
            // that lock for a long time, which made the matchmaking form look stuck.
            // A unique index on player_posts.booking_id protects the one-post-per-booking
            // rule for concurrent requests.
            $booking = Booking::with('venueCluster')
                ->whereKey($data['booking_id'])
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

            // A matchmaking post owns one persistent group chat. Approved
            // participants are added to this group later.
            $this->matchmakingChat->ensureGroup($post->load('booking.venueCluster'));

            DB::commit();

            // Notifications are a follow-up action. They must not keep the create
            // transaction open or turn a successful post into a failed request.
            try {
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'matchmaking_post_created',
                    'title' => 'Đăng bài giao lưu thành công',
                    'body' => 'Bài giao lưu của bạn cho lịch đặt sân ' . ($booking->venueCluster->name ?? '') . ' đã được đăng lên Cộng đồng.',
                    'reference_type' => 'player_post',
                    'reference_id' => $post->id,
                    'data' => ['action_url' => '/matchmaking-posts/' . $post->id . '/manage'],
                ]);
            } catch (\Throwable $notificationError) {
                report($notificationError);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Đăng bài thành công.',
                'data' => array_merge($post->toArray(), [
                    'group_chat_id' => $this->matchmakingChat->conversationFor($post)?->id,
                ]),
            ], 201);
        } catch (QueryException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'player_posts_booking_id_unique')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lịch đặt sân này đã có bài giao lưu.',
                ], 409);
            }

            \Illuminate\Support\Facades\Log::error('PlayerPost Create Error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo bài giao lưu. Vui lòng thử lại.',
            ], 500);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            \Illuminate\Support\Facades\Log::error('PlayerPost Create Error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể tạo bài giao lưu. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Update the text of a matchmaking post while it is still active.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $post = PlayerPost::query()->findOrFail($id);
        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền sửa bài giao lưu này.'], 403);
        }
        if (! in_array($post->status, ['open', 'full'], true)) {
            return response()->json(['message' => 'Bài giao lưu đã đóng và không thể sửa.'], 409);
        }
        if (! $post->booking || ! $this->isEligibleBooking($post->booking)) {
            return response()->json(['message' => 'Booking gốc không còn hợp lệ để sửa bài.'], 409);
        }

        $post->description = trim(strip_tags($data['content']));
        $post->save();

        return response()->json(['status' => 'success', 'message' => 'Đã cập nhật bài giao lưu.', 'data' => $post]);
    }

    /**
     * Close a post without deleting its participant history.
     */
    public function close(Request $request, $id): JsonResponse
    {
        $post = PlayerPost::with('booking')->findOrFail($id);
        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền đóng bài giao lưu này.'], 403);
        }
        if ($post->booking && $this->hasBookingStarted($post->booking)) {
            $this->synchronizeLifecycle($post);
            return response()->json(['message' => 'Bài giao lưu đã tự khóa khi booking bắt đầu.'], 409);
        }
        if (! in_array($post->status, ['open', 'full'], true)) {
            return response()->json(['message' => 'Bài giao lưu đã được đóng trước đó.'], 409);
        }

        $post->status = 'closed';
        $post->status_reason = 'closed_by_author';
        $post->save();

        return response()->json(['status' => 'success', 'message' => 'Đã đóng bài giao lưu.']);
    }

    /**
     * Withdraw the current user's pending/approved participation.
     */
    public function leave(Request $request, $id): JsonResponse
    {
        $userId = $request->user()->id;

        DB::beginTransaction();
        try {
            $post = PlayerPost::with('booking.venueCluster')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->synchronizeLifecycle($post);
            $post->refresh();
            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $participant || ! in_array($participant->status, ['pending', 'approved'], true)) {
                DB::rollBack();
                return response()->json(['message' => 'Bạn không có yêu cầu đang hoạt động trong bài này.'], 409);
            }

            $wasApproved = $participant->status === 'approved';
            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->update(['status' => 'cancelled', 'responded_at' => now(), 'left_at' => now(), 'updated_at' => now()]);

            if ($wasApproved && $post->booking && ! $this->hasBookingStarted($post->booking)) {
                $post->needed_players += 1;
                if ($post->status === 'full') $post->status = 'open';
                $post->save();
            }

            $this->matchmakingChat->markLeft($post, $userId, $request->user()->full_name ?? $request->user()->username);

            Notification::create([
                'user_id' => $post->author_id,
                'type' => 'matchmaking_leave',
                'title' => 'Có người rút khỏi kèo giao lưu',
                'body' => ($request->user()->full_name ?? $request->user()->username ?? 'Một người chơi') . ' đã rút khỏi bài giao lưu tại ' . ($post->booking->venueCluster->name ?? 'sân') . '.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
                'data' => ['action_url' => '/matchmaking-posts/' . $post->id . '/manage'],
            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Đã rút yêu cầu tham gia.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Không thể rút khỏi bài giao lưu. Vui lòng thử lại.'], 500);
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
        $this->synchronizeLifecycle($post);
        $post->refresh();

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

        $existing = DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->status !== 'cancelled') {
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
            $this->synchronizeLifecycle($post);
            $post->refresh();

            if ($post->status !== 'open' || $post->needed_players <= 0 || ! $post->booking || ! $this->isEligibleBooking($post->booking)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bài giao lưu này không còn nhận thêm người.',
                ], 409);
            }

            $inserted = $existing
                ? DB::table('player_post_participants')->where('post_id', $post->id)->where('user_id', $userId)->update([
                    'status' => 'pending',
                    'responded_at' => null,
                    'left_at' => null,
                    'updated_at' => now(),
                ])
                : DB::table('player_post_participants')->insertOrIgnore([
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
                'data' => ['action_url' => '/matchmaking-posts/' . $post->id . '/manage'],
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

        DB::beginTransaction();
        try {
            $post = PlayerPost::with('booking.venueCluster')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->synchronizeLifecycle($post);
            $post->refresh();

            $participants = DB::table('player_post_participants')
            ->join('users', 'player_post_participants.user_id', '=', 'users.id')
            ->where('post_id', $post->id)
            ->select(
                'player_post_participants.user_id',
                'player_post_participants.status',
                'player_post_participants.created_at',
                'player_post_participants.responded_at',
                'player_post_participants.left_at',
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
                'responded_at' => $p->responded_at,
                'left_at' => $p->left_at,
            ];
            });

            $response = response()->json([
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'status' => $post->status,
                'needed_players' => $post->needed_players,
                'venue_name' => $post->booking->venueCluster->name ?? 'Sân chưa xác định',
                'booking_date' => $post->booking->booking_date->format('Y-m-d'),
                'start_time' => substr($post->booking->start_time, 0, 5),
                'end_time' => substr($post->booking->end_time, 0, 5),
                'time' => substr($post->booking->start_time, 0, 5) . ' - ' . substr($post->booking->end_time, 0, 5) . ' · ' . $post->booking->booking_date->format('d/m/Y'),
                'booking_status' => $post->booking->status,
                'group_chat_id' => $this->matchmakingChat->conversationFor($post)?->id,
            ],
            'participants' => $data
            ]);
            DB::commit();
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Không thể tải danh sách yêu cầu. Vui lòng thử lại.'], 500);
        }
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
            $post = PlayerPost::with('booking.venueCluster')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->synchronizeLifecycle($post);
            $post->refresh();

            // Check current status
            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if (!$participant || $participant->status !== 'pending') {
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

            $approvedUser = \App\Models\User::query()->find($userId);
            $this->matchmakingChat->addMember($post, (int) $userId, $approvedUser?->full_name ?? $approvedUser?->username);

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
                'data' => ['action_url' => '/matchmaking-requests/' . $participant->id],
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
            $post = PlayerPost::with('booking')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->synchronizeLifecycle($post);
            $post->refresh();

            if ($post->booking && $this->hasBookingStarted($post->booking)) {
                DB::rollBack();
                return response()->json(['message' => 'Booking đã bắt đầu, không thể thay đổi thành viên của kèo.'], 409);
            }

            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->first();

            if (!$participant || !in_array($participant->status, ['pending', 'approved'], true)) {
                DB::rollBack();
                return response()->json(['message' => 'Yêu cầu không hợp lệ hoặc đã bị từ chối.'], 409);
            }

            $wasApproved = $participant->status === 'approved';

            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->update(['status' => 'rejected', 'responded_at' => now(), 'left_at' => now()]);

            // If it was approved before, restore needed_players
            if ($wasApproved) {
                $this->matchmakingChat->markLeft($post, $userId);
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
                'data' => ['action_url' => '/matchmaking-requests/' . $participant->id],
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
        return $booking->status === 'confirmed' && $this->isBeforeBookingStart($booking);
    }

    /** Close the post at start time and cancel pending requests. */
    private function synchronizeLifecycle(PlayerPost $post): void
    {
        if (! $post->booking) return;

        $started = $this->hasBookingStarted($post->booking);
        $ended = $this->hasBookingEnded($post->booking);
        if (! $started && ! $ended) return;

        $pendingParticipants = DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('status', 'pending')
            ->get(['id', 'user_id']);

        if ($pendingParticipants->isNotEmpty()) {
            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'cancelled',
                    'responded_at' => now(),
                    'left_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if (in_array($post->status, ['open', 'full'], true)) {
            $post->status = 'closed';
            $post->status_reason = $ended ? 'matchmaking_session_ended' : 'matchmaking_session_started';
            $post->save();
        }

        foreach ($pendingParticipants as $pendingParticipant) {
            Notification::create([
                'user_id' => $pendingParticipant->user_id,
                'type' => 'matchmaking_request_expired',
                'title' => 'Yêu cầu giao lưu đã tự hủy',
                'body' => $ended
                    ? 'Buổi giao lưu đã kết thúc nên yêu cầu tham gia của bạn đã được tự động hủy.'
                    : 'Đã đến giờ booking nên bài giao lưu đã khóa nhận thêm người và yêu cầu của bạn được tự động hủy.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
                'data' => ['action_url' => '/matchmaking-requests/' . $pendingParticipant->id],
            ]);
        }
    }

    private function isBeforeBookingStart(Booking $booking): bool
    {
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;
        return $date > $businessNow->toDateString()
            || ($date === $businessNow->toDateString() && substr((string) $booking->start_time, 0, 8) > $businessNow->toTimeString());
    }

    private function hasBookingStarted(Booking $booking): bool
    {
        return ! $this->isBeforeBookingStart($booking);
    }

    private function hasBookingEnded(Booking $booking): bool
    {
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;
        return $date < $businessNow->toDateString()
            || ($date === $businessNow->toDateString() && substr((string) $booking->end_time, 0, 8) <= $businessNow->toTimeString());
    }

    /** Only the post creator may dissolve the group after a completed booking. */
    public function dissolveGroup(Request $request, $id): JsonResponse
    {
        $post = PlayerPost::with('booking')->findOrFail($id);
        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Chỉ người tạo bài mới được giải tán nhóm.'], 403);
        }
        $this->synchronizeLifecycle($post);
        $post->refresh();
        if (! $post->booking || ! $this->hasBookingEnded($post->booking) || $post->booking->status !== 'completed') {
            return response()->json(['message' => 'Chỉ được giải tán nhóm sau khi booking đã kết thúc và hoàn thành.'], 409);
        }

        $this->matchmakingChat->dissolve($post);
        return response()->json(['status' => 'success', 'message' => 'Đã giải tán nhóm giao lưu.']);
    }
}
