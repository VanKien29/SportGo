<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\PlayerPost;
use App\Services\CommunityAuthorBadgeService;
use App\Services\MatchmakingChatService;
use App\Services\Memberships\SystemVipService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Events\MatchmakingUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlayerPostController extends Controller
{
    public function __construct(
        private CommunityAuthorBadgeService $authorBadges,
        private MatchmakingChatService $matchmakingChat,
        private SystemVipService $systemVip,
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
                'booking.venueCourt.courtType.parent',
                'booking.items.venueCourt.courtType.parent',
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
            $bookingPayload = $this->bookingPayload($post->booking);
            $approvedPlayers = (int) $post->approved_players_count;
            $neededPlayers = $this->effectiveNeededPlayers($post, $approvedPlayers);

            return [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'image_url' => $post->image_url,
                'image_path' => $post->image_path,
                'target_players' => $this->targetPlayers($post, $approvedPlayers),
                'needed_players' => $neededPlayers,
                'skill_level' => $post->skill_level ?? 'all',
                'cost_type' => $post->cost_type ?? 'free',
                'cost_per_player' => (float) ($post->cost_per_player ?? 0),
                'approved_players' => (int) $post->approved_players_count,
                'total_players' => $approvedPlayers + $neededPlayers,
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
                'booking' => $bookingPayload,
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

        // Chỉ lấy các booking sắp tới được xác nhận và còn cách giờ bắt đầu tối thiểu 60 phút
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $minStartThreshold = $businessNow->copy()->addMinutes(60);
        $thresholdDate = $minStartThreshold->toDateString();
        $thresholdTime = $minStartThreshold->toTimeString();

        $bookings = Booking::query()
            ->select(['id', 'booking_code', 'venue_cluster_id', 'venue_court_id', 'booking_date', 'start_time', 'end_time', 'total_price'])
            ->with([
                'venueCluster:id,name,address',
                'venueCourt.courtType.parent',
                'items.venueCourt.courtType.parent',
            ])
            ->where('customer_id', $userId)
            ->where('status', 'confirmed')
            ->where(function ($query) use ($thresholdDate, $thresholdTime) {
                $query->where('booking_date', '>', $thresholdDate)
                    ->orWhere(function ($todayQuery) use ($thresholdDate, $thresholdTime) {
                        $todayQuery->where('booking_date', '=', $thresholdDate)
                            ->where('start_time', '>=', $thresholdTime);
                    });
            })
            ->whereNotIn('id', PlayerPost::query()->select('booking_id'))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        $eligible = $bookings->map(function ($booking) {
            $bookingPayload = $this->bookingPayload($booking);
            $court = $booking->venueCourt;
            $courtInfo = $this->resolveCourtTypeInfo($court);
            $suggestedPlayers = $court?->courtType?->player_count ?? 0;

            return [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code ?? ('#BK' . $booking->id),
                'venue_id' => $booking->venue_cluster_id,
                'venue_name' => $booking->venueCluster->name ?? 'Unknown',
                'location' => $booking->venueCluster->address ?? null,
                'court_name' => $court?->name ?? '',
                'sport_name' => $courtInfo['sport_name'],
                'court_type_name' => $courtInfo['court_type_name'],
                'sport_icon' => $courtInfo['sport_icon'],
                'suggested_players' => $suggestedPlayers,
                'total_price' => (float) ($booking->total_price ?? 0),
                'date' => $bookingPayload['date'],
                'time' => $bookingPayload['time'],
                'court_names' => $bookingPayload['court_names'],
                'courts' => $bookingPayload['courts'],
                'time_ranges' => $bookingPayload['time_ranges'],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $eligible,
        ]);
    }

    /**
     * Return every matchmaking post created by the authenticated player.
     *
     * Unlike the public feed, this includes closed and expired posts so the
     * account page can be used as a reliable history/management view.
     */
    public function myPosts(Request $request): JsonResponse
    {
        $posts = PlayerPost::query()
            ->with([
                'booking.venueCluster:id,name,address',
                'booking.venueCourt.courtType.parent',
                'booking.items.venueCourt.courtType.parent',
            ])
            ->withCount([
                'participants as approved_players_count' => fn ($query) => $query
                    ->where('player_post_participants.status', 'approved'),
                'participants as pending_requests_count' => fn ($query) => $query
                    ->where('player_post_participants.status', 'pending'),
            ])
            ->where('author_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(min(max((int) $request->input('per_page', 15), 5), 50));

        // Keep the displayed status in sync when the deadline has passed,
        // including for posts that are no longer visible in the public feed.
        $posts->getCollection()->each(fn (PlayerPost $post) => $this->synchronizeLifecycle($post));

        $data = $posts->getCollection()->map(function (PlayerPost $post) {
            $booking = $post->booking;
            $approvedPlayers = (int) ($post->approved_players_count ?? 0);
            $neededPlayers = $this->effectiveNeededPlayers($post, $approvedPlayers);

            return [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'image_url' => $post->image_url,
                'target_players' => $this->targetPlayers($post, $approvedPlayers),
                'needed_players' => $neededPlayers,
                'approved_players' => $approvedPlayers,
                'pending_requests' => (int) ($post->pending_requests_count ?? 0),
                'total_players' => $approvedPlayers + $neededPlayers,
                'skill_level' => $post->skill_level ?? 'all',
                'cost_type' => $post->cost_type ?? 'free',
                'cost_per_player' => (float) ($post->cost_per_player ?? 0),
                'status' => $post->status,
                'status_reason' => $post->status_reason,
                'created_at' => $post->created_at,
                'booking' => $this->bookingPayload($booking),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
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
            ->leftJoin('venue_courts as court', 'court.id', '=', 'booking.venue_court_id')
            ->leftJoin('court_types as court_type', 'court_type.id', '=', 'court.court_type_id')
            ->leftJoin('court_types as sport_type', 'sport_type.id', '=', 'court_type.parent_id')
            ->join('users as author', 'author.id', '=', 'post.author_id')
            ->where('participant.user_id', $request->user()->id)
            ->select([
                'participant.id', 'participant.post_id', 'participant.status', 'participant.message',
                'participant.created_at', 'participant.responded_at', 'participant.left_at', 'participant.removal_reason',
                'post.title', 'post.description', 'post.image_path', 'post.status as post_status',
                'post.target_players', 'post.needed_players', 'post.skill_level', 'post.cost_type', 'post.cost_per_player', 'post.lock_lead_minutes',
                'booking.booking_date', 'booking.start_time', 'booking.end_time', 'booking.total_price',
                'venue.id as venue_id', 'venue.name as venue_name', 'venue.address as venue_address',
                'court.name as court_name',
                'sport_type.name as parent_sport_name', 'sport_type.icon_key as parent_sport_icon',
                'court_type.name as direct_court_type_name', 'court_type.icon_key as direct_sport_icon',
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
            ->leftJoin('venue_courts as court', 'court.id', '=', 'booking.venue_court_id')
            ->leftJoin('court_types as court_type', 'court_type.id', '=', 'court.court_type_id')
            ->leftJoin('court_types as sport_type', 'sport_type.id', '=', 'court_type.parent_id')
            ->join('users as author', 'author.id', '=', 'post.author_id')
            ->where('participant.id', $id)
            ->where('participant.user_id', $request->user()->id)
            ->select([
                'participant.id', 'participant.post_id', 'participant.status', 'participant.message',
                'participant.created_at', 'participant.responded_at', 'participant.left_at', 'participant.removal_reason',
                'post.title', 'post.description', 'post.image_path', 'post.status as post_status',
                'post.target_players', 'post.needed_players', 'post.skill_level', 'post.cost_type', 'post.cost_per_player', 'post.lock_lead_minutes',
                'booking.booking_date', 'booking.start_time', 'booking.end_time', 'booking.total_price',
                'venue.id as venue_id', 'venue.name as venue_name', 'venue.address as venue_address',
                'court.name as court_name',
                'sport_type.name as parent_sport_name', 'sport_type.icon_key as parent_sport_icon',
                'court_type.name as direct_court_type_name', 'court_type.icon_key as direct_sport_icon',
                'author.id as author_id', 'author.full_name as author_name', 'author.username as author_username',
                'author.avatar_url as author_avatar',
            ])
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $this->participantPayload($item)]);
    }

    private function participantPayload(object $item): array
    {
        $sportName = $item->parent_sport_name ?? $item->direct_court_type_name ?? 'Thể thao';
        $courtTypeName = $item->direct_court_type_name ?? 'Sân tiêu chuẩn';
        if ($item->parent_sport_name && preg_match('/\((.*?)\)/u', $courtTypeName, $matches)) {
            $courtTypeName = trim($matches[1]);
        } elseif ($item->parent_sport_name && str_starts_with(mb_strtolower($courtTypeName), mb_strtolower($sportName))) {
            $courtTypeName = trim(mb_substr($courtTypeName, mb_strlen($sportName)));
            $courtTypeName = ltrim($courtTypeName, " -·:()");
        }
        if (empty($courtTypeName)) {
            $courtTypeName = 'Sân tiêu chuẩn';
        }
        $sportIcon = $item->parent_sport_icon ?? $item->direct_sport_icon ?? 'activity';

        $postObj = PlayerPost::with(['booking.venueCluster', 'booking.venueCourt.courtType.parent', 'booking.items.venueCourt.courtType.parent'])->find($item->post_id);
        $groupChatId = $postObj ? $this->matchmakingChat->conversationFor($postObj)?->id : null;
        $bookingPayload = $postObj ? $this->bookingPayload($postObj->booking) : [
            'date' => $item->booking_date,
            'start_time' => substr((string) $item->start_time, 0, 5),
            'end_time' => substr((string) $item->end_time, 0, 5),
            'time' => substr((string) $item->start_time, 0, 5) . ' - ' . substr((string) $item->end_time, 0, 5),
            'venue_id' => $item->venue_id,
            'venue_name' => $item->venue_name,
            'venue_address' => $item->venue_address,
            'court_name' => $item->court_name ?? '',
            'sport_name' => $sportName,
            'court_type_name' => $courtTypeName,
            'sport_icon' => $sportIcon,
            'total_price' => (float) ($item->total_price ?? 0),
        ];
        $targetPlayers = (int) ($item->target_players ?? $item->needed_players);
        $approvedPlayers = $postObj ? (int) $postObj->participants()->wherePivot('status', 'approved')->count() : 0;

        return [
            'id' => $item->id,
            'post_id' => $item->post_id,
            'status' => $item->status,
            'message' => $item->message,
            'removal_reason' => $item->removal_reason ?? null,
            'created_at' => $item->created_at,
            'responded_at' => $item->responded_at,
            'left_at' => $item->left_at,
            'group_chat_id' => $groupChatId,
            'post' => [
                'title' => $item->title,
                'description' => $item->description,
                'image_url' => $item->image_path ? asset('storage/' . $item->image_path) : null,
                'status' => $item->post_status,
                'target_players' => $targetPlayers,
                'needed_players' => max(0, $targetPlayers - $approvedPlayers),
                'skill_level' => $item->skill_level ?? 'all',
                'cost_type' => $item->cost_type ?? 'free',
                'cost_per_player' => (float) ($item->cost_per_player ?? 0),
                'lock_lead_minutes' => (int) ($item->lock_lead_minutes ?? 30),
            ],
            'booking' => $bookingPayload,
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
            'lock_lead_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'skill_level' => ['nullable', 'string', 'in:all,beginner,intermediate,advanced'],
            'cost_type' => ['nullable', 'string', 'in:free,split,custom'],
            'cost_per_player' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:15360'],
        ], [
            'content.min' => 'Mô tả bài viết cần có ít nhất 10 ký tự nếu nhập.',
            'image.max' => 'Ảnh đính kèm không được vượt quá 15MB.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('player_posts', 'public');
        }

        $content = isset($data['content']) ? trim(strip_tags($data['content'])) : '';

        // Thẩm định nội dung bằng Gemini AI
        $gemini = app(\App\Services\GeminiService::class);
        $mediaUrls = $imagePath ? [asset('storage/' . $imagePath)] : [];
        $aiResult = $gemini->moderateCommunityPost($content ?: 'Tìm người giao lưu thể thao', [], $mediaUrls);

        // Nếu AI xác định nội dung vi phạm tiêu chuẩn nghiêm trọng
        if ($aiResult['verdict'] === 'rejected') {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'Nội dung không được duyệt do vi phạm tiêu chuẩn cộng đồng: ' . ($aiResult['reason'] ?? 'Nội dung không phù hợp.'),
                'ai_verdict' => 'rejected',
                'ai_reason' => $aiResult['reason'] ?? null,
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Do not lock the booking row here. Booking creation/status updates can hold
            // that lock for a long time, which made the matchmaking form look stuck.
            // A unique index on player_posts.booking_id protects the one-post-per-booking
            // rule for concurrent requests.
            $booking = Booking::with(['venueCluster', 'venueCourt.courtType.parent'])
                ->whereKey($data['booking_id'])
                ->firstOrFail();

            if ((string) $booking->customer_id !== (string) $userId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn không có quyền tạo bài giao lưu cho lịch đặt này.',
                ], 403);
            }

            if (! $this->isEligibleBooking($booking, 60)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lịch đặt sân phải còn cách giờ bắt đầu ít nhất 1 tiếng để đủ thời gian tìm người chơi.',
                ], 409);
            }

            if (PlayerPost::where('booking_id', $booking->id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lịch đặt sân này đã có bài giao lưu.',
                ], 409);
            }

            try {
                $quota = $this->systemVip->reserveMatchmakingPostQuota($request->user());
            } catch (ValidationException $exception) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'code' => 'POST_QUOTA_EXCEEDED',
                    'message' => $exception->errors()['quota'][0] ?? 'Bạn đã hết hạn mức đăng bài giao lưu trong tháng.',
                ], 422);
            }

            $costType = $data['cost_type'] ?? 'free';
            $costPerPlayer = 0;
            if ($costType === 'split') {
                $totalPrice = (float) ($booking->total_price ?? 0);
                $totalPeople = (int) $data['required_players'] + 1;
                $costPerPlayer = $totalPeople > 0 ? (round(($totalPrice / $totalPeople) / 1000) * 1000) : 0;
            } elseif ($costType === 'custom') {
                $costPerPlayer = (float) ($data['cost_per_player'] ?? 0);
            }

            $post = PlayerPost::create([
                'booking_id' => $booking->id,
                'author_id' => $userId,
                'title' => 'Tìm người giao lưu',
                'description' => $content,
                'image_path' => $imagePath,
                'target_players' => $data['required_players'],
                'needed_players' => $data['required_players'],
                'lock_lead_minutes' => (int) ($data['lock_lead_minutes'] ?? 30),
                'skill_level' => $data['skill_level'] ?? 'all',
                'cost_type' => $costType,
                'cost_per_player' => $costPerPlayer,
                'status' => 'open', // Trạng thái mở để tuyển người
                'status_reason' => $aiResult['verdict'] === 'needs_review' ? ($aiResult['reason'] ?? 'Cần xem xét thêm') : null,
                'ai_verdict' => $aiResult['verdict'] ?? null,
                'ai_score' => $aiResult['score'] ?? null,
                'ai_summary' => $aiResult['summary'] ?? null,
                'ai_flags' => $aiResult['flags'] ?? [],
                'ai_reviewed_at' => now(),
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
                'booking' => $this->bookingPayload($booking),
                    'quota' => $quota,
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
            'content' => ['nullable', 'string', 'min:10', 'max:2000'],
            'target_players' => ['required', 'integer', 'min:1', 'max:50'],
            'skill_level' => ['nullable', 'string', 'in:all,beginner,intermediate,advanced'],
            'cost_type' => ['nullable', 'string', 'in:free,split,custom'],
            'cost_per_player' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'lock_lead_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'remove_image' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:15360'],
        ]);

        $post = PlayerPost::with('booking')->findOrFail($id);
        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền sửa bài giao lưu này.'], 403);
        }
        if (! in_array($post->status, ['open', 'full'], true)) {
            return response()->json(['message' => 'Bài giao lưu đã đóng và không thể sửa.'], 409);
        }
        $this->synchronizeLifecycle($post);
        $post->refresh();
        if (! in_array($post->status, ['open', 'full'], true) || ! $post->booking || $this->hasBookingStarted($post->booking) || $this->isLockDeadlineReached($post)) {
            return response()->json(['message' => 'Booking gốc không còn hợp lệ để sửa bài.'], 409);
        }

        $approvedPlayers = (int) DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('status', 'approved')
            ->count();
        if ((int) $data['target_players'] < $approvedPlayers) {
            return response()->json(['message' => 'Số người cần tuyển không được nhỏ hơn số người đã được duyệt.'], 422);
        }

        $oldImagePath = $post->image_path;
        $newImagePath = $request->hasFile('image')
            ? $request->file('image')->store('player_posts', 'public')
            : null;

        $costType = $data['cost_type'] ?? 'free';
        $costPerPlayer = match ($costType) {
            'free' => 0,
            'custom' => (float) ($data['cost_per_player'] ?? 0),
            'split' => round(((float) ($post->booking->total_price ?? 0) / ((int) $data['target_players'] + 1)) / 1000) * 1000,
            default => 0,
        };
        $post->fill([
            'description' => trim(strip_tags((string) ($data['content'] ?? ''))),
            'target_players' => (int) $data['target_players'],
            'skill_level' => $data['skill_level'] ?? 'all',
            'cost_type' => $costType,
            'cost_per_player' => $costPerPlayer,
            'lock_lead_minutes' => (int) ($data['lock_lead_minutes'] ?? $post->lock_lead_minutes ?? 30),
        ]);
        if ($newImagePath) {
            $post->image_path = $newImagePath;
        } elseif ($request->boolean('remove_image')) {
            $post->image_path = null;
        }
        $post->save();
        $this->reconcileRoster($post, $approvedPlayers);

        if (($newImagePath || $request->boolean('remove_image')) && $oldImagePath && $oldImagePath !== $post->image_path) {
            Storage::disk('public')->delete($oldImagePath);
        }

        $this->broadcastMatchmakingUpdate((int) $post->id, 'post_updated', [
            'target_players' => $post->target_players,
            'needed_players' => $post->needed_players,
            'status' => $post->status,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã cập nhật bài giao lưu.',
            'data' => array_merge($post->toArray(), [
                'booking' => $this->bookingPayload($post->booking),
            ]),
        ]);
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

        $this->broadcastMatchmakingUpdate((int) $post->id, 'post_closed');

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
                ->update(['status' => 'left', 'responded_at' => now(), 'left_at' => now(), 'updated_at' => now()]);

            if ($wasApproved && $post->booking && ! $this->hasBookingStarted($post->booking)) {
                $this->reconcileRoster($post);
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

            $this->broadcastMatchmakingUpdate((int) $post->id, 'participant_left', ['user_id' => $userId]);

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

        if (! in_array($post->status, ['open', 'full'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài giao lưu này đã đóng hoặc bị hủy.',
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

        if ($existing && ! in_array($existing->status, ['cancelled', 'left', 'expired', 'rejected'], true)) {
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

            if (! in_array($post->status, ['open', 'full'], true) || ! $post->booking || ! $this->isEligibleBooking($post->booking)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bài giao lưu này đã đóng hoặc không còn hiệu lực.',
                ], 409);
            }

            $inserted = ($existing && in_array($existing->status, ['cancelled', 'left', 'expired', 'rejected'], true))
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

            $this->broadcastMatchmakingUpdate((int) $post->id, 'participant_joined', ['user_id' => $userId]);

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
            $post = PlayerPost::with(['author', 'booking.venueCluster', 'booking.venueCourt.courtType.parent', 'booking.items.venueCourt.courtType.parent'])->whereKey($id)->lockForUpdate()->firstOrFail();
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
                'player_post_participants.removal_reason',
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
                'removal_reason' => $p->removal_reason,
            ];
            });

            $court = $post->booking?->venueCourt;
            $courtInfo = $this->resolveCourtTypeInfo($court);

            $response = response()->json([
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'description' => $post->description,
                'image_url' => $post->image_url,
                'image_path' => $post->image_path,
                'status' => $post->status,
                'target_players' => $this->targetPlayers($post),
                'needed_players' => $this->effectiveNeededPlayers($post),
                'lock_lead_minutes' => (int) ($post->lock_lead_minutes ?? 30),
                'skill_level' => $post->skill_level ?? 'all',
                'cost_type' => $post->cost_type ?? 'free',
                'cost_per_player' => (float) ($post->cost_per_player ?? 0),
                'venue_name' => $post->booking->venueCluster->name ?? 'Sân chưa xác định',
                'venue_address' => $post->booking->venueCluster->address ?? '',
                'venue_id' => $post->booking->venueCluster->id ?? null,
                'booking_id' => $post->booking_id,
                'court_name' => $court?->name ?? '',
                'sport_name' => $courtInfo['sport_name'],
                'court_type_name' => $courtInfo['court_type_name'],
                'sport_icon' => $courtInfo['sport_icon'],
                'booking_date' => $post->booking->booking_date->format('Y-m-d'),
                'start_time' => substr($post->booking->start_time, 0, 5),
                'end_time' => substr($post->booking->end_time, 0, 5),
                'time' => $this->bookingPayload($post->booking)['time'] . ', ' . $post->booking->booking_date->format('d/m/Y'),
                'booking_details' => $this->bookingPayload($post->booking),
                'booking_status' => $post->booking->status,
                'group_chat_id' => $this->matchmakingChat->ensureGroup($post)->id,
                'author' => [
                    'id' => $post->author?->id,
                    'name' => $post->author?->full_name ?? $post->author?->username ?? 'Chủ bài',
                    'avatar' => $post->author?->avatar_url,
                ],
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

            if (! in_array($post->status, ['open', 'full'], true) || ! $post->booking || ! $this->isEligibleBooking($post->booking)) {
                DB::rollBack();
                return response()->json(['message' => 'Bài giao lưu đã đóng hoặc không còn hiệu lực.'], 409);
            }

            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->update(['status' => 'approved', 'responded_at' => now()]);

            $approvedUser = \App\Models\User::query()->find($userId);
            $this->matchmakingChat->addMember($post, (int) $userId, $approvedUser?->full_name ?? $approvedUser?->username);

            $this->reconcileRoster($post);

            // Notify user
            $groupChat = $this->matchmakingChat->conversationFor($post);
            Notification::create([
                'user_id' => $userId,
                'type' => 'matchmaking_join_approved',
                'title' => 'Yêu cầu ghép kèo được chấp nhận',
                'body' => 'Chủ bài viết đã đồng ý cho bạn tham gia ghép kèo tại sân ' . ($post->booking->venueCluster->name ?? 'sân') . '. Bạn đã được thêm vào nhóm chat.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
                'data' => [
                    'action_url' => $groupChat ? ('/messages?conversation_id=' . $groupChat->id) : ('/matchmaking-requests/' . $participant->id),
                    'conversation_id' => $groupChat?->id,
                ],
            ]);

            DB::commit();

            $this->broadcastMatchmakingUpdate((int) $post->id, 'participant_approved', ['user_id' => $userId]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Không thể duyệt người tham gia. Vui lòng thử lại.'], 500);
        }
    }

    /** Remove an approved participant while preserving their history. */
    public function removeParticipant(Request $request, $id, $userId): JsonResponse
    {
        $post = PlayerPost::with('booking.venueCluster')->findOrFail($id);
        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $reason = trim((string) $request->input('reason', ''));
        DB::beginTransaction();
        try {
            $post = PlayerPost::with('booking.venueCluster')->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->synchronizeLifecycle($post);
            $post->refresh();
            if (! $post->booking || $this->hasBookingStarted($post->booking)) {
                DB::rollBack();
                return response()->json(['message' => 'Booking đã bắt đầu, không thể xóa thành viên.'], 409);
            }

            $participant = DB::table('player_post_participants')
                ->where('post_id', $post->id)->where('user_id', $userId)->lockForUpdate()->first();
            if (! $participant || $participant->status !== 'approved') {
                DB::rollBack();
                return response()->json(['message' => 'Chỉ có thể xóa thành viên đã được duyệt.'], 409);
            }
            if ((string) $post->author_id === (string) $userId) {
                DB::rollBack();
                return response()->json(['message' => 'Không thể xóa chủ bài.'], 409);
            }

            $now = now();
            DB::table('player_post_participants')->where('id', $participant->id)->update([
                'status' => 'removed_by_author',
                'responded_at' => $now,
                'left_at' => $now,
                'removal_reason' => $reason !== '' ? $reason : null,
                'updated_at' => $now,
            ]);
            $this->reconcileRoster($post);
            $this->matchmakingChat->markLeft($post, $userId);

            Notification::create([
                'user_id' => $userId,
                'type' => 'matchmaking_member_removed',
                'title' => 'Bạn đã được xóa khỏi nhóm giao lưu',
                'body' => $reason !== '' ? 'Chủ bài đã xóa bạn khỏi nhóm. Lý do: ' . $reason : 'Chủ bài đã xóa bạn khỏi nhóm giao lưu.',
                'reference_type' => 'player_post',
                'reference_id' => $post->id,
                'data' => ['action_url' => '/matchmaking-requests/' . $participant->id],
            ]);

            DB::commit();
            $this->broadcastMatchmakingUpdate((int) $post->id, 'participant_removed', ['user_id' => (int) $userId]);
            return response()->json(['status' => 'success', 'message' => 'Đã xóa thành viên khỏi nhóm.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['message' => 'Không thể xóa thành viên. Vui lòng thử lại.'], 500);
        }
    }

    /** Reject a pending participant. */
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

            if (!$participant || $participant->status !== 'pending') {
                DB::rollBack();
                return response()->json(['message' => 'Yêu cầu không hợp lệ hoặc đã bị từ chối.'], 409);
            }

            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('user_id', $userId)
                ->update(['status' => 'rejected', 'responded_at' => now(), 'left_at' => now()]);

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

            $this->broadcastMatchmakingUpdate((int) $post->id, 'participant_rejected', ['user_id' => $userId]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Không thể từ chối yêu cầu. Vui lòng thử lại.'], 500);
        }
    }

    private function targetPlayers(PlayerPost $post, ?int $approvedPlayers = null): int
    {
        $approvedPlayers ??= (int) DB::table('player_post_participants')
            ->where('post_id', $post->id)->where('status', 'approved')->count();
        return max(0, (int) ($post->target_players ?? ((int) $post->needed_players + $approvedPlayers)));
    }

    private function effectiveNeededPlayers(PlayerPost $post, ?int $approvedPlayers = null): int
    {
        $approvedPlayers ??= (int) DB::table('player_post_participants')
            ->where('post_id', $post->id)->where('status', 'approved')->count();
        return max(0, $this->targetPlayers($post, $approvedPlayers) - $approvedPlayers);
    }

    /** Keep the denormalized slot counter and status derived from the roster. */
    private function reconcileRoster(PlayerPost $post, ?int $approvedPlayers = null): array
    {
        $approvedPlayers ??= (int) DB::table('player_post_participants')
            ->where('post_id', $post->id)->where('status', 'approved')->count();
        $targetPlayers = $this->targetPlayers($post, $approvedPlayers);
        $neededPlayers = max(0, $targetPlayers - $approvedPlayers);
        $post->target_players = $targetPlayers;
        $post->needed_players = $neededPlayers;
        if (in_array($post->status, ['open', 'full'], true)) {
            $post->status = $neededPlayers === 0 ? 'full' : 'open';
        }
        $post->save();
        return [$approvedPlayers, $neededPlayers];
    }

    /** Return every court and its own time range for a multi-court booking. */
    private function bookingPayload(?Booking $booking): array
    {
        if (! $booking) return [];
        $booking->loadMissing([
            'venueCluster:id,name,address',
            'venueCourt.courtType.parent',
            'items.venueCourt.courtType.parent',
        ]);

        $items = $booking->items;
        if ($items->isEmpty()) {
            $items = collect([(object) [
                'venueCourt' => $booking->venueCourt,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
            ]]);
        }
        $courts = $items->map(function ($item) {
            $court = $item->venueCourt;
            $info = $this->resolveCourtTypeInfo($court);
            return [
                'id' => $court?->id,
                'name' => $court?->name ?? '',
                'sport_name' => $info['sport_name'],
                'court_type_name' => $info['court_type_name'],
                'sport_icon' => $info['sport_icon'],
                'start_time' => substr((string) ($item->start_time ?? ''), 0, 5),
                'end_time' => substr((string) ($item->end_time ?? ''), 0, 5),
            ];
        })->values();
        $ranges = $courts->map(fn ($court) => [
            'court_name' => $court['name'],
            'start_time' => $court['start_time'],
            'end_time' => $court['end_time'],
            'time' => trim($court['start_time'] . ' - ' . $court['end_time']),
        ])->values();
        $uniqueRanges = $ranges->map(fn ($range) => $range['time'])->unique()->values();
        $date = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;
        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code ?? ('#BK' . $booking->id),
            'date' => $date,
            'start_time' => substr((string) $booking->start_time, 0, 5),
            'end_time' => substr((string) $booking->end_time, 0, 5),
            'time' => $uniqueRanges->count() === 1 ? $uniqueRanges->first() : $uniqueRanges->implode(' / '),
            'venue_id' => $booking->venue_cluster_id,
            'venue_name' => $booking->venueCluster?->name ?? 'Sân chưa xác định',
            'venue_address' => $booking->venueCluster?->address ?? '',
            'court_name' => $courts->pluck('name')->filter()->unique()->implode(' + '),
            'court_names' => $courts->pluck('name')->filter()->unique()->values()->all(),
            'courts' => $courts->all(),
            'time_ranges' => $ranges->all(),
            'total_price' => (float) ($booking->total_price ?? 0),
            'booking_status' => $booking->status,
        ];
    }

    private function isEligibleBooking(Booking $booking, int $minLeadMinutes = 60): bool
    {
        if ($booking->status !== 'confirmed') return false;
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $bookingDateStr = $booking->booking_date?->format('Y-m-d') ?? (string) $booking->booking_date;
        $bookingStart = \Carbon\Carbon::parse($bookingDateStr . ' ' . $booking->start_time, (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        return $bookingStart->diffInMinutes($businessNow, false) <= -$minLeadMinutes;
    }

    private function isLockDeadlineReached(PlayerPost $post): bool
    {
        if (! $post->booking) return true;
        $businessNow = now((string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $bookingDateStr = $post->booking->booking_date?->format('Y-m-d') ?? (string) $post->booking->booking_date;
        $bookingStart = \Carbon\Carbon::parse($bookingDateStr . ' ' . $post->booking->start_time, (string) config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $deadline = $bookingStart->copy()->subMinutes((int) ($post->lock_lead_minutes ?? 30));
        return $businessNow->gte($deadline);
    }

    /** Close the post at lock deadline or start time and cancel pending requests. */
    private function synchronizeLifecycle(PlayerPost $post): void
    {
        if (! $post->booking) return;

        $deadlineReached = $this->isLockDeadlineReached($post);
        $started = $this->hasBookingStarted($post->booking);
        $ended = $this->hasBookingEnded($post->booking);
        if (! $deadlineReached && ! $started && ! $ended) return;

        $pendingParticipants = DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('status', 'pending')
            ->get(['id', 'user_id']);

        if ($pendingParticipants->isNotEmpty()) {
            DB::table('player_post_participants')
                ->where('post_id', $post->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'expired',
                    'responded_at' => now(),
                    'left_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if (in_array($post->status, ['open', 'full'], true)) {
            $post->status = 'closed';
            $post->status_reason = $ended 
                ? 'matchmaking_session_ended' 
                : ($started ? 'matchmaking_session_started' : 'matchmaking_deadline_reached');
            $post->save();
        }

        foreach ($pendingParticipants as $pendingParticipant) {
            Notification::create([
                'user_id' => $pendingParticipant->user_id,
                'type' => 'matchmaking_request_expired',
                'title' => 'Yêu cầu giao lưu đã tự hủy',
                'body' => $ended
                    ? 'Buổi giao lưu đã kết thúc nên yêu cầu tham gia của bạn đã được tự động hủy.'
                    : 'Đã đến hạn chốt đăng ký của bài giao lưu nên yêu cầu của bạn đã được tự động hủy.',
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

    /** Only the post creator may dissolve the group. */
    public function dissolveGroup(Request $request, $id): JsonResponse
    {
        $post = PlayerPost::with('booking')->findOrFail($id);
        if ((string) $post->author_id !== (string) $request->user()->id) {
            return response()->json(['message' => 'Chỉ người tạo bài mới được giải tán nhóm.'], 403);
        }

        if (in_array($post->status, ['open', 'full'], true)) {
            $post->forceFill([
                'status' => 'closed',
                'status_reason' => 'matchmaking_group_dissolved',
            ])->save();
        }

        DB::table('player_post_participants')
            ->where('post_id', $post->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'responded_at' => now(),
                'left_at' => now(),
                'updated_at' => now(),
            ]);

        $this->matchmakingChat->dissolve($post);

        $this->broadcastMatchmakingUpdate((int) $post->id, 'group_dissolved');

        return response()->json(['status' => 'success', 'message' => 'Đã giải tán nhóm giao lưu.']);
    }

    private function broadcastMatchmakingUpdate(int $postId, string $action, array $data = []): void
    {
        try {
            broadcast(new MatchmakingUpdated($postId, $action, $data));
        } catch (\Throwable $exception) {
            // Realtime delivery is best effort; the database mutation has
            // already succeeded and must not be returned as a 500.
            report($exception);
        }
    }

    /**
     * Resolve sport and clean court type names from a VenueCourt model.
     */
    private function resolveCourtTypeInfo(?\App\Models\VenueCourt $court): array
    {
        $courtType = $court?->courtType;
        $sportName = $courtType?->parent?->name ?? $courtType?->name ?? 'Thể thao';
        $rawName = $courtType?->name ?? 'Sân tiêu chuẩn';
        $courtTypeName = $rawName;

        if ($courtType?->parent && preg_match('/\((.*?)\)/u', $rawName, $matches)) {
            $courtTypeName = trim($matches[1]);
        } elseif ($courtType?->parent && str_starts_with(mb_strtolower($rawName), mb_strtolower($sportName))) {
            $courtTypeName = trim(mb_substr($rawName, mb_strlen($sportName)));
            $courtTypeName = ltrim($courtTypeName, " -·:()");
        }

        if (empty($courtTypeName)) {
            $courtTypeName = 'Sân tiêu chuẩn';
        }

        $sportIcon = $courtType?->parent?->icon_key ?? $courtType?->icon_key ?? 'activity';

        return [
            'sport_name' => $sportName,
            'court_type_name' => $courtTypeName,
            'sport_icon' => $sportIcon,
        ];
    }
}
