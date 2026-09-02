<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVenuePostRequest;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\Hashtag;
use App\Models\ModerationConfig;
use App\Models\VenuePost;
use App\Services\CommunityAuthorBadgeService;
use App\Services\GeminiService;
use App\Services\VenuePostService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VenuePostController extends Controller
{
    public function __construct(
        private VenuePostService $venuePostService,
        private CommunityAuthorBadgeService $authorBadges
    ) {}

    /**
     * Trả về một bảng tin chung nhưng vẫn giữ nguyên các bài venue_posts hiện có.
     * Bài tự do từ community_posts dùng khóa public "community-{id}" để không va
     * chạm id số của venue_posts trên cùng route.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'feed_type' => ['nullable', Rule::in(['community_post', 'venue_post'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'venue_cluster_id' => ['nullable', 'integer', 'exists:venue_clusters,id'],
            'author_id' => ['nullable', 'integer'],
            'post_type' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:50'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ], [
            'feed_type.in' => 'Loại bảng tin không hợp lệ.',
            'page.integer' => 'Số trang phải là số nguyên.',
            'page.min' => 'Số trang phải từ 1 trở lên.',
            'page.max' => 'Số trang vượt quá giới hạn cho phép.',
            'per_page.integer' => 'Số bài mỗi trang phải là số nguyên.',
            'per_page.min' => 'Mỗi trang phải có ít nhất 1 bài.',
            'per_page.max' => 'Mỗi trang chỉ được tải tối đa 30 bài.',
            'venue_cluster_id.integer' => 'Cụm sân không hợp lệ.',
            'venue_cluster_id.exists' => 'Không tìm thấy cụm sân cần xem.',
            'author_id.integer' => 'Tác giả không hợp lệ.',
            'post_type.max' => 'Loại bài đăng không được vượt quá 50 ký tự.',
            'category.max' => 'Danh mục không được vượt quá 50 ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 100 ký tự.',
        ]);
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $feedType = $validated['feed_type'] ?? ($request->filled('venue_cluster_id') ? 'venue_post' : 'community_post');

        if ($feedType === 'venue_post' && ! $request->filled('venue_cluster_id')) {
            return response()->json([
                'message' => 'Bài đăng của cụm sân chỉ được tải trong trang chi tiết của một cụm sân.',
                'errors' => ['venue_cluster_id' => ['Vui lòng chọn cụm sân cần xem bài đăng.']],
            ], 422);
        }

        if ($feedType === 'community_post'
            && ($request->filled('venue_cluster_id') || $request->filled('post_type'))) {
            return response()->json([
                'message' => 'Bộ lọc bài cụm sân không thể dùng cho bảng tin cộng đồng.',
                'errors' => ['feed_type' => ['Vui lòng bỏ bộ lọc cụm sân hoặc chọn feed_type=venue_post.']],
            ], 422);
        }

        $userId = auth('sanctum')->id();
        if ($feedType === 'venue_post') {
            $query = VenuePost::with([
                'media',
                'author:id,full_name,username,avatar_url',
                'venueCluster:id,name',
                'hashtags',
            ])
                ->where('status', 'published')
                ->where('venue_cluster_id', $validated['venue_cluster_id'])
                ->when($request->author_id, fn ($query) => $query->where('author_id', $request->author_id))
                ->when($request->post_type, fn ($query) => $query->where('post_type', $request->post_type))
                ->when($request->category, function ($query) use ($request) {
                    $query->whereHas('hashtags', fn ($hashtagQuery) => $hashtagQuery->where('name', $request->category));
                })
                ->when($request->filled('keyword'), function ($query) use ($request) {
                    $keyword = trim((string) $request->keyword);
                    $query->where(function ($nested) use ($keyword) {
                        $nested->where('title', 'like', "%{$keyword}%")
                            ->orWhere('short_description', 'like', "%{$keyword}%")
                            ->orWhere('content', 'like', "%{$keyword}%");
                    });
                });

            $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
            $posts = $paginator->getCollection();
            $likesAvailable = Schema::hasTable('venue_post_likes');
            $likedLookup = $this->likedLookup('venue_post_likes', $posts->pluck('id'), $userId);
            $authorBadges = $this->authorBadges->lookup($posts->pluck('author_id'));
            $paginator->setCollection($posts->map(
                fn (VenuePost $post) => $this->normalizeVenuePost(
                    $post,
                    $likesAvailable,
                    $likedLookup,
                    $authorBadges[(string) $post->author_id] ?? []
                )
            ));

            return response()->json($paginator);
        }

        if (! Schema::hasTable('community_posts')) {
            return response()->json(new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            ));
        }

        $likesAvailable = Schema::hasTable('community_post_likes');
        $commentsAvailable = Schema::hasTable('community_post_comments');
        $query = CommunityPost::with([
            'media',
            'author:id,full_name,username,avatar_url',
            'hashtags',
        ])
            ->where('status', 'published')
            ->when($request->author_id, fn ($query) => $query->where('author_id', $request->author_id))
            ->when($request->category, function ($query) use ($request) {
                $query->whereHas('hashtags', fn ($hashtagQuery) => $hashtagQuery->where('name', $request->category));
            })
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $query->where('content', 'like', '%'.trim((string) $request->keyword).'%');
            });

        if ($likesAvailable) {
            $query->withCount('likes');
        }
        if ($commentsAvailable) {
            $query->withCount([
                'comments as visible_comment_count' => fn ($commentQuery) => $commentQuery->where('status', 'visible'),
            ]);
        }

        $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        $posts = $paginator->getCollection();
        $likedLookup = $this->likedLookup('community_post_likes', $posts->pluck('id'), $userId);
        $authorBadges = $this->authorBadges->lookup($posts->pluck('author_id'));
        $paginator->setCollection($posts->map(
            fn (CommunityPost $post) => $this->normalizeCommunityPost(
                $post,
                $likesAvailable,
                $likedLookup,
                $authorBadges[(string) $post->author_id] ?? []
            )
        ));

        return response()->json($paginator);
    }

    public function show(string $slug)
    {
        $communityId = $this->communityPostId($slug);
        if ($communityId !== null) {
            return $this->showCommunityPost($communityId);
        }

        $likesAvailable = Schema::hasTable('venue_post_likes');
        $relations = [
            'media',
            'author:id,full_name,username,avatar_url',
            'venueCluster:id,name',
            'hashtags',
            'topLevelComments' => function ($query) {
                $query->with(['user:id,full_name,username,avatar_url', 'replies.user:id,full_name,username,avatar_url']);
            },
        ];

        if ($likesAvailable) {
            $relations[] = 'likers:id,full_name,username,avatar_url';
        }

        $post = VenuePost::with($relations)
            ->where(function ($query) use ($slug) {
                $query->where('slug', $slug)->orWhere('id', $slug);
            })
            ->firstOrFail();

        if ($post->status !== 'published') {
            abort(403, 'Bài viết không tồn tại hoặc chưa được xuất bản.');
        }

        $post->increment('view_count');
        $post->setAttribute('feed_type', 'venue_post');
        $post->setAttribute('entity_id', $post->id);
        $post->setAttribute('likes_available', $likesAvailable);
        $post->setAttribute(
            'author_badges',
            $this->authorBadges->lookup([$post->author_id])[(string) $post->author_id] ?? []
        );
        $post->setAttribute(
            'is_liked',
            $likesAvailable && auth('sanctum')->check()
                ? $post->likers->contains('id', auth('sanctum')->id())
                : false
        );

        if (! $likesAvailable) {
            $post->setRelation('likers', collect());
        }

        $commentPeople = $post->topLevelComments->flatMap(function ($comment) {
            return collect([$comment->user])->concat($comment->replies->pluck('user'));
        });
        $this->decoratePeopleWithAuthorBadges($commentPeople->concat($post->likers));

        return response()->json(['data' => $post]);
    }

    public function myPosts(Request $request)
    {
        $user = $request->user();
        if (! Schema::hasTable('community_posts')) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'pending_review', 'published', 'rejected', 'hidden', 'deleted', 'trash'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $validated['status'] ?? 'all';
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;

        if ($status === 'deleted' || $status === 'trash') {
            $query = CommunityPost::onlyTrashed()
                ->with([
                    'media',
                    'author:id,full_name,username,avatar_url',
                    'hashtags',
                ])
                ->where('author_id', $user->id);
        } else {
            $query = CommunityPost::with([
                'media',
                'author:id,full_name,username,avatar_url',
                'hashtags',
            ])
                ->where('author_id', $user->id)
                ->when($status !== 'all', fn ($q) => $q->where('status', $status));
        }

        $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        $posts = $paginator->getCollection();

        $likesAvailable = Schema::hasTable('community_post_likes');
        $likedLookup = $this->likedLookup('community_post_likes', $posts->pluck('id'), $user->id);
        $authorBadges = $this->authorBadges->lookup([$user->id]);

        $paginator->setCollection($posts->map(
            fn (CommunityPost $post) => $this->normalizeCommunityPost(
                $post,
                $likesAvailable,
                $likedLookup,
                $authorBadges[(string) $user->id] ?? []
            )
        ));

        return response()->json($paginator);
    }

    /**
     * Player tạo bài tự do trong community_posts. venue_posts vẫn dành cho luồng
     * bài cụm sân của Owner, nơi venue_cluster_id là bắt buộc trong schema hiện tại.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', VenuePost::class);

        if (! Schema::hasTable('community_posts')) {
            return response()->json([
                'message' => 'Chức năng bài viết cộng đồng chưa sẵn sàng trên hệ thống.',
            ], 503);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'min:5', 'max:200', 'regex:/^[^\<\>]+$/u'],
            'short_description' => ['nullable', 'string', 'min:10', 'max:500', 'regex:/^[^\<\>]+$/u'],
            'content' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $stripped = trim(html_entity_decode(strip_tags($value)));
                    if (mb_strlen($stripped) < 20) {
                        $fail('Nội dung thực tế phải có ít nhất 20 ký tự.');
                    }
                    if (mb_strlen($value) > 30000) {
                        $fail('Nội dung quá dài, tối đa 30000 ký tự.');
                    }
                },
            ],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\s\-\p{L}]+$/u'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'post_type' => ['nullable', 'string', Rule::in(['news'])],
            'is_draft' => ['nullable', 'declined'],
        ], [
            'post_type.in' => 'Bài chia sẻ cộng đồng không hỗ trợ loại bài này.',
            'is_draft.declined' => 'Luồng bài cộng đồng hiện chưa hỗ trợ lưu nháp.',
            'images.max' => 'Bạn chỉ có thể tải lên tối đa 10 ảnh cho mỗi bài viết.',
        ]);

        $content = trim(strip_tags($validated['content']));
        $tags = $validated['tags'] ?? [];

        // Kiểm tra chống spam cho bài viết cộng đồng
        $user = $request->user();
        $isAdmin = $user && method_exists($user, 'roles') && $user->roles()->whereIn('roles.name', ['admin', 'super_admin'])->exists();

        if (! $isAdmin) {
            // 1. Giãn cách thời gian giữa 2 bài đăng (Cooldown 30 giây)
            $latestPost = CommunityPost::withTrashed()
                ->where('author_id', $user->id)
                ->latest('created_at')
                ->first();

            if ($latestPost && $latestPost->created_at) {
                $secondsSince = (int) now()->diffInSeconds($latestPost->created_at);
                $cooldown = 30;
                if ($secondsSince < $cooldown) {
                    $wait = $cooldown - $secondsSince;
                    return response()->json([
                        'message' => "Bạn vừa đăng bài cách đây ít giây. Vui lòng đợi thêm {$wait} giây trước khi đăng bài mới.",
                        'errors' => ['content' => ["Vui lòng đợi thêm {$wait} giây trước khi đăng bài tiếp theo."]],
                    ], 429);
                }
            }

            // 2. Chặn bài đăng trùng lặp nội dung trong vòng 30 phút
            $normalizedNew = preg_replace('/\s+/u', ' ', mb_strtolower($content));
            $recentDuplicate = CommunityPost::withTrashed()
                ->where('author_id', $user->id)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->first(function ($p) use ($normalizedNew) {
                    $normalizedExisting = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $p->content)));
                    return $normalizedExisting === $normalizedNew;
                });

            if ($recentDuplicate) {
                return response()->json([
                    'message' => 'Nội dung bài viết bị trùng lặp với bài bạn vừa đăng gần đây. Vui lòng chia sẻ nội dung khác.',
                    'errors' => ['content' => ['Nội dung bài viết bị trùng lặp với bài bạn vừa đăng gần đây.']],
                ], 422);
            }

            // 3. Giới hạn số lượng bài đăng trong ngày (Tối đa 10 bài/ngày)
            $dailyLimit = 10;
            $todayCount = CommunityPost::where('author_id', $user->id)
                ->whereDate('created_at', now()->toDateString())
                ->count();

            if ($todayCount >= $dailyLimit) {
                return response()->json([
                    'message' => "Bạn đã đạt giới hạn tối đa {$dailyLimit} bài đăng trong ngày. Vui lòng quay lại vào ngày mai.",
                    'errors' => ['content' => ["Đã đạt giới hạn {$dailyLimit} bài đăng trong ngày."]],
                ], 429);
            }
        }

        // Thẩm định bằng Gemini AI
        $gemini = app(GeminiService::class);
        $aiResult = $gemini->moderateCommunityPost($content, $tags);

        $status = 'pending_review';
        $statusReason = null;

        if ($aiResult['verdict'] === 'approved' && $aiResult['score'] >= 85) {
            $status = 'published';
        } elseif ($aiResult['verdict'] === 'rejected') {
            $status = 'rejected';
            $statusReason = $aiResult['reason'] ?? 'Nội dung vi phạm quy chuẩn cộng đồng.';
        } else {
            $status = 'pending_review';
        }

        $post = DB::transaction(function () use ($request, $validated, $content, $status, $statusReason, $aiResult) {
            $communityPost = CommunityPost::create([
                'author_id' => $request->user()->id,
                'content' => $content,
                'status' => $status,
                'status_reason' => $statusReason,
                'ai_verdict' => $aiResult['verdict'] ?? null,
                'ai_score' => $aiResult['score'] ?? null,
                'ai_summary' => $aiResult['summary'] ?? null,
                'ai_flags' => $aiResult['flags'] ?? [],
                'ai_reviewed_at' => now(),
            ]);

            if (! empty($validated['tags']) && Schema::hasTable('post_hashtags')) {
                $hashtagIds = collect($validated['tags'])
                    ->map(fn ($tag) => trim($tag))
                    ->filter()
                    ->unique()
                    ->map(function ($tagName) {
                        return Hashtag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => Str::slug($tagName)]
                        )->id;
                    })
                    ->values()
                    ->all();

                $communityPost->hashtags()->syncWithPivotValues($hashtagIds, ['post_type' => 'community_posts']);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $this->storeCommunityThumbnail($communityPost, $imageFile);
                }
            } elseif ($request->hasFile('thumbnail')) {
                $this->storeCommunityThumbnail($communityPost, $request->file('thumbnail'));
            }

            return $communityPost->load(['media', 'author:id,full_name,username,avatar_url', 'hashtags']);
        });

        $data = $this->normalizeCommunityPost(
            $post,
            Schema::hasTable('community_post_likes'),
            [],
            $this->authorBadges->lookup([$post->author_id])[(string) $post->author_id] ?? []
        );

        $responseMessage = match ($post->status) {
            'published' => 'Bài viết đã được AI kiểm duyệt và xuất bản thành công.',
            'rejected' => 'Bài viết bị từ chối do vi phạm quy chuẩn cộng đồng: ' . ($post->status_reason ?? ''),
            default => 'Bài viết đã được gửi và đang chờ quản trị viên duyệt.',
        };

        return response()->json([
            'message' => $responseMessage,
            'data' => $data,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id) ?? (is_numeric($id) ? (int) $id : null);
        if ($communityId !== null && CommunityPost::where('id', $communityId)->exists()) {
            $post = CommunityPost::findOrFail($communityId);
            $isAdmin = $request->user()->roles()->whereIn('roles.name', ['admin', 'super_admin'])->exists();
            abort_unless($isAdmin || (string) $post->author_id === (string) $request->user()->id, 403);

            if ($post->status === 'hidden' && ! $isAdmin) {
                return response()->json([
                    'message' => 'Bài viết đã bị quản trị viên khóa và không thể chỉnh sửa.',
                ], 403);
            }

            $validated = $request->validate([
                'content' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $stripped = trim(html_entity_decode(strip_tags($value)));
                        if (mb_strlen($stripped) < 20) {
                            $fail('Nội dung thực tế phải có ít nhất 20 ký tự.');
                        }
                    },
                ],
                'tags' => ['nullable', 'array', 'max:10'],
                'tags.*' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\s\-\p{L}]+$/u'],
                'images' => ['nullable', 'array', 'max:10'],
                'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'removed_media_ids' => ['nullable', 'array'],
                'removed_media_ids.*' => ['integer'],
            ]);

            $content = trim(strip_tags($validated['content']));
            $tags = $validated['tags'] ?? [];

            // Thẩm định bằng Gemini AI
            $gemini = app(GeminiService::class);
            $aiResult = $gemini->moderateCommunityPost($content, $tags);

            $status = 'pending_review';
            $statusReason = null;

            if ($aiResult['verdict'] === 'approved' && $aiResult['score'] >= 85) {
                $status = 'published';
            } elseif ($aiResult['verdict'] === 'rejected') {
                $status = 'rejected';
                $statusReason = $aiResult['reason'] ?? 'Nội dung vi phạm quy chuẩn cộng đồng.';
            } else {
                $status = 'pending_review';
            }

            $post->content = $content;
            $post->status = $status;
            $post->status_reason = $statusReason;
            $post->ai_verdict = $aiResult['verdict'] ?? null;
            $post->ai_score = $aiResult['score'] ?? null;
            $post->ai_summary = $aiResult['summary'] ?? null;
            $post->ai_flags = $aiResult['flags'] ?? [];
            $post->ai_reviewed_at = now();
            $post->edited_at = now();
            $post->edit_count = ((int) $post->edit_count) + 1;
            $post->save();

            // Sync hashtags
            if (isset($validated['tags']) && Schema::hasTable('post_hashtags')) {
                $hashtagIds = collect($validated['tags'])
                    ->map(fn ($tag) => trim($tag))
                    ->filter()
                    ->unique()
                    ->map(function ($tagName) {
                        return Hashtag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => Str::slug($tagName)]
                        )->id;
                    })
                    ->values()
                    ->all();
                $post->hashtags()->syncWithPivotValues($hashtagIds, ['post_type' => 'community_posts']);
            }

            // Remove deleted media
            if (! empty($validated['removed_media_ids'])) {
                $mediaToRemove = $post->media()->whereIn('id', $validated['removed_media_ids'])->get();
                foreach ($mediaToRemove as $media) {
                    Storage::disk('public')->delete((string) $media->getRawOriginal('file_path'));
                    $media->delete();
                }
            }

            // Store newly added images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $this->storeCommunityThumbnail($post, $imageFile);
                }
            }

            $data = $this->normalizeCommunityPost(
                $post->load(['media', 'author:id,full_name,username,avatar_url', 'hashtags']),
                Schema::hasTable('community_post_likes'),
                [],
                $this->authorBadges->lookup([$post->author_id])[(string) $post->author_id] ?? []
            );

            $responseMessage = match ($status) {
                'published' => 'Bài viết đã được AI kiểm duyệt và cập nhật thành công.',
                'rejected' => 'Bài viết bị từ chối do vi phạm quy chuẩn cộng đồng: ' . ($post->status_reason ?? ''),
                default => 'Bài viết đã được chỉnh sửa và chuyển sang hàng chờ kiểm duyệt.',
            };

            return response()->json([
                'message' => $responseMessage,
                'data' => $data,
            ]);
        }

        $post = VenuePost::findOrFail($id);
        Gate::authorize('update', $post);

        try {
            $post = $this->venuePostService->updatePost(
                $post,
                $request->all(),
                $request->user(),
                $request->file('thumbnail')
            );

            return response()->json(['message' => 'Bài viết đã được cập nhật.', 'data' => $post->load(['media', 'hashtags'])]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['status' => [$exception->getMessage()]],
            ], 422);
        }
    }

    public function restore(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id) ?? (is_numeric($id) ? (int) $id : null);
        if ($communityId !== null) {
            $post = CommunityPost::onlyTrashed()->findOrFail($communityId);
            $isAdmin = $request->user()->roles()->whereIn('roles.name', ['admin', 'super_admin'])->exists();
            abort_unless($isAdmin || (string) $post->author_id === (string) $request->user()->id, 403);

            $post->restore();

            return response()->json([
                'message' => 'Đã khôi phục bài viết thành công.',
                'data' => $this->normalizeCommunityPost(
                    $post->load(['media', 'author:id,full_name,username,avatar_url', 'hashtags']),
                    Schema::hasTable('community_post_likes'),
                    [],
                    $this->authorBadges->lookup([$post->author_id])[(string) $post->author_id] ?? []
                ),
            ]);
        }

        return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
    }

    public function appeal(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id) ?? (is_numeric($id) ? (int) $id : null);
        if ($communityId !== null) {
            $post = CommunityPost::findOrFail($communityId);
            abort_unless((string) $post->author_id === (string) $request->user()->id, 403);

            $validated = $request->validate([
                'note' => ['required', 'string', 'min:5', 'max:500'],
            ], [
                'note.required' => 'Vui lòng nhập lời nhắn giải trình / đề xuất duyệt lại.',
                'note.min' => 'Lời nhắn phải có ít nhất 5 ký tự.',
                'note.max' => 'Lời nhắn không được vượt quá 500 ký tự.',
            ]);

            $post->update([
                'status' => 'pending_review',
                'appeal_note' => trim($validated['note']),
                'appealed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Đã gửi yêu cầu xem xét lại tới ban quản trị thành công.',
                'data' => $this->normalizeCommunityPost(
                    $post->fresh(['media', 'author:id,full_name,username,avatar_url', 'hashtags']),
                    Schema::hasTable('community_post_likes'),
                    [],
                    $this->authorBadges->lookup([$post->author_id])[(string) $post->author_id] ?? []
                ),
            ]);
        }

        return response()->json(['message' => 'Không tìm thấy bài viết.'], 404);
    }

    public function destroy(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id) ?? (is_numeric($id) ? (int) $id : null);
        if ($communityId !== null) {
            $post = CommunityPost::withTrashed()->findOrFail($communityId);
            $isAdmin = $request->user()->roles()->whereIn('roles.name', ['admin', 'super_admin'])->exists();
            abort_unless($isAdmin || (string) $post->author_id === (string) $request->user()->id, 403);

            if ($request->boolean('force') || $post->trashed()) {
                foreach ($post->media as $media) {
                    Storage::disk('public')->delete((string) $media->getRawOriginal('file_path'));
                    $media->delete();
                }
                $post->forceDelete();

                return response()->json(['message' => 'Đã xóa vĩnh viễn bài viết.']);
            }

            // Soft delete
            $post->delete();

            return response()->json(['message' => 'Đã chuyển bài viết vào thùng rác.']);
        }

        $post = VenuePost::findOrFail($id);
        Gate::authorize('delete', $post);
        $this->venuePostService->deletePost($post, $request->user());

        return response()->json(['message' => 'Bài viết đã được xóa.']);
    }

    public function comment(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id);
        $isCommunityPost = $communityId !== null;
        $commentTable = $isCommunityPost ? 'community_post_comments' : 'venue_post_comments';

        if (! Schema::hasTable($commentTable)) {
            return response()->json(['message' => 'Chức năng bình luận chưa sẵn sàng trên hệ thống.'], 503);
        }

        $post = $isCommunityPost
            ? CommunityPost::where('status', 'published')->findOrFail($communityId)
            : VenuePost::where('status', 'published')->findOrFail($id);
        $postForeignKey = $isCommunityPost ? 'post_id' : 'venue_post_id';

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($commentTable, 'id')->where(
                    fn ($query) => $query->where($postForeignKey, $post->id)
                ),
            ],
        ]);

        $content = trim(strip_tags($validated['content']));
        if ($content === '') {
            return response()->json([
                'message' => 'Nội dung bình luận không được chỉ chứa khoảng trắng.',
                'errors' => ['content' => ['Nội dung bình luận không được chỉ chứa khoảng trắng.']],
            ], 422);
        }

        $commentResult = DB::transaction(function () use (
            $commentTable,
            $postForeignKey,
            $post,
            $request,
            $validated,
            $content,
            $isCommunityPost
        ) {
            $payload = [
                $postForeignKey => $post->id,
                'user_id' => $request->user()->id,
                'content' => $content,
                'parent_id' => $validated['parent_id'] ?? null,
                'status' => $isCommunityPost ? 'visible' : 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $newCommentId = DB::table($commentTable)->insertGetId($payload);
            $commentCount = DB::table($commentTable)
                ->where($postForeignKey, $post->id)
                ->where('status', $isCommunityPost ? 'visible' : 'published')
                ->count();
            $post->update(['comment_count' => $commentCount]);

            return [
                'id' => $newCommentId,
                'comment_count' => $commentCount,
            ];
        });
        $commentId = $commentResult['id'];
        $commentAuthor = $request->user()->only(['id', 'full_name', 'username', 'avatar_url']);
        $commentAuthor['author_badges'] = $this->authorBadges
            ->lookup([$request->user()->id])[(string) $request->user()->id] ?? [];

        return response()->json([
            'message' => 'Đã gửi bình luận.',
            'data' => [
                'id' => $commentId,
                'content' => $content,
                'created_at' => now()->toISOString(),
                'comment_count' => $commentResult['comment_count'],
                'user' => $commentAuthor,
            ],
        ]);
    }

    public function toggleLike(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id);
        if ($communityId !== null) {
            return $this->toggleCommunityLike($request, $communityId);
        }

        if (! Schema::hasTable('venue_post_likes')) {
            return response()->json([
                'message' => 'Chức năng thích bài viết đang chờ hoàn tất cập nhật dữ liệu hệ thống.',
            ], 503);
        }

        $userId = $request->user()->id;

        return DB::transaction(function () use ($id, $userId) {
            $post = VenuePost::where('status', 'published')->lockForUpdate()->findOrFail($id);
            $likeQuery = DB::table('venue_post_likes')
                ->where('post_id', $post->id)
                ->where('user_id', $userId);
            $isLiked = $likeQuery->exists();

            if ($isLiked) {
                $likeQuery->delete();
            } else {
                DB::table('venue_post_likes')->insert([
                    'id' => (string) Str::uuid(),
                    'post_id' => $post->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $likeCount = DB::table('venue_post_likes')->where('post_id', $post->id)->count();
            $post->update(['like_count' => $likeCount]);

            return response()->json([
                'message' => $isLiked ? 'Đã bỏ thích.' : 'Đã thích bài viết.',
                'data' => ['is_liked' => ! $isLiked, 'like_count' => $likeCount],
            ]);
        });
    }

    private function showCommunityPost(int $id)
    {
        $likesAvailable = Schema::hasTable('community_post_likes');
        $commentsAvailable = Schema::hasTable('community_post_comments');
        $postQuery = CommunityPost::with([
            'media',
            'author:id,full_name,username,avatar_url',
            'hashtags',
        ]);

        if ($likesAvailable) {
            $postQuery->withCount('likes');
        }
        if ($commentsAvailable) {
            $postQuery->withCount([
                'comments as visible_comment_count' => fn ($commentQuery) => $commentQuery->where('status', 'visible'),
            ]);
        }

        $post = $postQuery->findOrFail($id);

        if ($post->status !== 'published') {
            abort(403, 'Bài viết không tồn tại hoặc chưa được xuất bản.');
        }

        $post->increment('view_count');
        $comments = collect();
        if (Schema::hasTable('community_post_comments')) {
            $comments = CommunityPostComment::where('post_id', $post->id)
                ->where('status', 'visible')
                ->whereNull('parent_id')
                ->with([
                    'user:id,full_name,username,avatar_url',
                    'replies' => fn ($query) => $query->where('status', 'visible')->oldest(),
                    'replies.user:id,full_name,username,avatar_url',
                ])
                ->latest()
                ->get();
        }

        $userId = auth('sanctum')->id();
        $likedLookup = $this->likedLookup('community_post_likes', collect([$post->id]), $userId);
        $authorBadges = $this->authorBadges->lookup([$post->author_id]);
        $data = $this->normalizeCommunityPost(
            $post,
            $likesAvailable,
            $likedLookup,
            $authorBadges[(string) $post->author_id] ?? []
        );
        $likers = $likesAvailable
            ? DB::table('community_post_likes')
                ->join('users', 'users.id', '=', 'community_post_likes.user_id')
                ->where('community_post_likes.post_id', $post->id)
                ->select('users.id', 'users.full_name', 'users.username', 'users.avatar_url')
                ->get()
            : collect();
        $commentPeople = $comments->flatMap(function ($comment) {
            return collect([$comment->user])->concat($comment->replies->pluck('user'));
        });
        $this->decoratePeopleWithAuthorBadges($commentPeople->concat($likers));
        $data['top_level_comments'] = $comments;
        $data['likers'] = $likers;

        return response()->json(['data' => $data]);
    }

    private function toggleCommunityLike(Request $request, int $id)
    {
        if (! Schema::hasTable('community_post_likes')) {
            return response()->json([
                'message' => 'Chức năng thích bài viết đang chờ hoàn tất cập nhật dữ liệu hệ thống.',
            ], 503);
        }

        $userId = $request->user()->id;

        return DB::transaction(function () use ($id, $userId) {
            $post = CommunityPost::where('status', 'published')->lockForUpdate()->findOrFail($id);
            $likeQuery = DB::table('community_post_likes')
                ->where('post_id', $post->id)
                ->where('user_id', $userId);
            $isLiked = $likeQuery->exists();

            if ($isLiked) {
                $likeQuery->delete();
            } else {
                DB::table('community_post_likes')->insert([
                    'post_id' => $post->id,
                    'user_id' => $userId,
                    'created_at' => now(),
                ]);
            }

            $likeCount = DB::table('community_post_likes')->where('post_id', $post->id)->count();
            $post->update(['like_count' => $likeCount]);

            return response()->json([
                'message' => $isLiked ? 'Đã bỏ thích.' : 'Đã thích bài viết.',
                'data' => ['is_liked' => ! $isLiked, 'like_count' => $likeCount],
            ]);
        });
    }

    private function normalizeVenuePost(
        VenuePost $post,
        bool $likesAvailable,
        array $likedLookup,
        array $authorBadges = []
    ): array {
        $data = $post->toArray();
        $data['feed_type'] = 'venue_post';
        $data['entity_id'] = $post->id;
        $data['likes_available'] = $likesAvailable;
        $data['is_liked'] = isset($likedLookup[(string) $post->id]);
        $data['author_badges'] = $authorBadges;
        $data['is_edited'] = (bool) ($post->updated_at && $post->created_at && $post->updated_at->diffInMinutes($post->created_at) > 1);
        $data['edited_at'] = $post->updated_at;

        return $data;
    }

    private function normalizeCommunityPost(
        CommunityPost $post,
        bool $likesAvailable,
        array $likedLookup,
        array $authorBadges = []
    ): array {
        $data = $post->toArray();
        $plainContent = trim(strip_tags((string) $post->content));
        $publicId = 'community-'.$post->id;
        $data['id'] = $publicId;
        $data['entity_id'] = $post->id;
        $data['feed_type'] = 'community_post';
        $data['slug'] = $publicId;
        $data['title'] = Str::limit($plainContent, 80);
        $data['short_description'] = Str::limit($plainContent, 500);
        $data['post_type'] = 'news';
        $data['published_at'] = $post->created_at;
        $data['venue_cluster_id'] = null;
        $data['venue_cluster'] = null;
        if (array_key_exists('likes_count', $data)) {
            $data['like_count'] = (int) $data['likes_count'];
        }
        if (array_key_exists('visible_comment_count', $data)) {
            $data['comment_count'] = (int) $data['visible_comment_count'];
        }
        $data['likes_available'] = $likesAvailable;
        $data['is_liked'] = isset($likedLookup[(string) $post->id]);
        $data['author_badges'] = $authorBadges;
        $data['is_edited'] = (bool) ($post->edited_at !== null || $post->edit_count > 0);
        $data['edited_at'] = $post->edited_at;
        $data['is_deleted'] = (bool) $post->trashed();
        $data['deleted_at'] = $post->deleted_at;
        $data['ai_verdict'] = $post->ai_verdict;
        $data['ai_score'] = $post->ai_score;
        $data['ai_summary'] = $post->ai_summary;
        $data['ai_flags'] = $post->ai_flags;
        $data['ai_reviewed_at'] = $post->ai_reviewed_at;
        $data['appeal_note'] = $post->appeal_note;
        $data['appealed_at'] = $post->appealed_at;
        $data['rejection_source'] = $post->status === 'rejected'
            ? (($post->reviewed_by === null && $post->ai_verdict === 'rejected') ? 'ai' : 'admin')
            : null;

        return $data;
    }

    private function likedLookup(string $table, Collection $postIds, ?int $userId): array
    {
        if (! $userId || $postIds->isEmpty() || ! Schema::hasTable($table)) {
            return [];
        }

        return array_fill_keys(
            DB::table($table)
                ->where('user_id', $userId)
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->map(fn ($postId) => (string) $postId)
                ->all(),
            true
        );
    }

    private function decoratePeopleWithAuthorBadges(iterable $people): void
    {
        $people = collect($people)
            ->filter(fn ($person) => $person && data_get($person, 'id'))
            ->unique(fn ($person) => (string) data_get($person, 'id'))
            ->values();
        $lookup = $this->authorBadges->lookup($people->map(fn ($person) => data_get($person, 'id')));

        $people->each(function ($person) use ($lookup): void {
            $badges = $lookup[(string) data_get($person, 'id')] ?? [];
            if (method_exists($person, 'setAttribute')) {
                $person->setAttribute('author_badges', $badges);

                return;
            }

            $person->author_badges = $badges;
        });
    }

    private function communityPostId(string $publicId): ?int
    {
        return preg_match('/^community-(\d+)$/', $publicId, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    private function storeCommunityThumbnail(CommunityPost $post, $thumbnail): void
    {
        $extension = strtolower($thumbnail->getClientOriginalExtension() ?: 'webp');
        $filename = uniqid('community_', true).'.'.$extension;
        $path = $thumbnail->storeAs('community_posts', $filename, 'public');

        $post->media()->create([
            'collection' => 'thumbnail',
            'file_name' => $thumbnail->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $thumbnail->getClientMimeType() ?: 'image/'.$extension,
            'file_size' => $thumbnail->getSize() ?: 0,
        ]);
    }
}
