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
use App\Services\VenuePostService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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

        $paginator = $query->latest()->paginate($perPage, ['*'], 'page', $page);
        $posts = $paginator->getCollection();
        $likesAvailable = Schema::hasTable('community_post_likes');
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
            'post_type' => ['nullable', 'string', Rule::in(['news'])],
            'is_draft' => ['nullable', 'declined'],
        ], [
            'post_type.in' => 'Bài chia sẻ cộng đồng không hỗ trợ loại bài này.',
            'is_draft.declined' => 'Luồng bài cộng đồng hiện chưa hỗ trợ lưu nháp.',
        ]);

        $content = trim(strip_tags($validated['content']));
        $status = 'pending_review';
        if (Schema::hasTable('moderation_configs')) {
            $requireModeration = ModerationConfig::where('key', 'require_community_post_moderation')->value('value');
            if ($requireModeration === 'false' || $requireModeration === false) {
                $status = 'published';
            }
        }

        $post = DB::transaction(function () use ($request, $validated, $content, $status) {
            $communityPost = CommunityPost::create([
                'author_id' => $request->user()->id,
                'content' => $content,
                'status' => $status,
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

            if ($request->hasFile('thumbnail')) {
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

        return response()->json([
            'message' => $post->status === 'published'
                ? 'Bài viết đã được đăng.'
                : 'Bài viết đã được gửi và đang chờ kiểm duyệt.',
            'data' => $data,
        ], 201);
    }

    public function update(UpdateVenuePostRequest $request, string $id)
    {
        if ($this->communityPostId($id) !== null) {
            return response()->json([
                'message' => 'Chỉnh sửa bài cộng đồng chưa được hỗ trợ trong luồng này.',
            ], 409);
        }

        $post = VenuePost::findOrFail($id);
        Gate::authorize('update', $post);

        try {
            $post = $this->venuePostService->updatePost(
                $post,
                $request->validated(),
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

    public function destroy(Request $request, string $id)
    {
        $communityId = $this->communityPostId($id);
        if ($communityId !== null) {
            $post = CommunityPost::findOrFail($communityId);
            $isAdmin = $request->user()->roles()->whereIn('roles.name', ['admin', 'super_admin'])->exists();
            abort_unless($isAdmin || (string) $post->author_id === (string) $request->user()->id, 403);

            foreach ($post->media as $media) {
                Storage::disk('public')->delete((string) $media->getRawOriginal('file_path'));
                $media->delete();
            }
            $post->delete();

            return response()->json(['message' => 'Bài viết đã được xóa.']);
        }

        $post = VenuePost::findOrFail($id);
        Gate::authorize('delete', $post);
        $this->venuePostService->deletePost($post, $request->user());

        return response()->json(['message' => 'Bài viết đã được xóa.']);
    }

    public function comment(Request $request, string $id)
    {
        $post = VenuePost::where('status', 'published')->findOrFail($id);
        $user = $request->user();
        
        $parentId = $request->input('parent_id');

        $commentId = DB::table('venue_post_comments')->insertGetId([
            'venue_post_id' => $post->id,
            'user_id' => $user->id,
            'content' => strip_tags($request->input('content')),
            'parent_id' => $parentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $post->increment('comment_count');
        
        $userName = $user->full_name ?: $user->username;

        if ($parentId) {
            $parentComment = DB::table('venue_post_comments')->where('id', $parentId)->first();
            if ($parentComment && $parentComment->user_id !== $user->id) {
                \App\Models\Notification::query()->create([
                    'user_id' => $parentComment->user_id,
                    'type' => 'comment_reply',
                    'title' => 'Ai đó đã trả lời bình luận của bạn',
                    'body' => $userName . ' đã trả lời bình luận của bạn trên cộng đồng.',
                    'reference_type' => 'venue_posts',
                    'reference_id' => $post->id,
                    'data' => isset($post->slug) ? ['slug' => $post->slug] : null,
                    'is_read' => false,
                ]);
            }
        } else {
            if ($post->author_id !== $user->id) {
                \App\Models\Notification::query()->create([
                    'user_id' => $post->author_id,
                    'type' => 'post_comment',
                    'title' => 'Ai đó đã bình luận bài viết của bạn',
                    'body' => $userName . ' đã bình luận về bài viết của bạn trên cộng đồng.',
                    'reference_type' => 'venue_posts',
                    'reference_id' => $post->id,
                    'data' => isset($post->slug) ? ['slug' => $post->slug] : null,
                    'is_read' => false,
                ]);
            }
        }

        $commentId = DB::transaction(function () use (
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

            return $newCommentId;
        });
        $commentAuthor = $request->user()->only(['id', 'full_name', 'username', 'avatar_url']);
        $commentAuthor['author_badges'] = $this->authorBadges
            ->lookup([$request->user()->id])[(string) $request->user()->id] ?? [];

        return response()->json([
            'message' => 'Đã gửi bình luận.',
            'data' => [
                'id' => $commentId,
                'content' => $content,
                'created_at' => now()->toISOString(),
                'user' => $commentAuthor,
            ],
        ]);
    }

    public function toggleLike(Request $request, string $id)
    {
        $post = VenuePost::where('status', 'published')->findOrFail($id);
        $user = $request->user();

        $like = DB::table('venue_post_likes')->where('post_id', $post->id)->where('user_id', $user->id)->first();

        if ($like) {
            DB::table('venue_post_likes')->where('post_id', $post->id)->where('user_id', $user->id)->delete();
            $post->decrement('like_count');
            return response()->json(['message' => 'Đã bỏ thích.']);
        } else {
            DB::table('venue_post_likes')->insert([
                'id' => Str::uuid(),
                'post_id' => $post->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $post->increment('like_count');
            
            if ($post->author_id !== $user->id) {
                $userName = $user->full_name ?: $user->username;
                \App\Models\Notification::query()->create([
                    'user_id' => $post->author_id,
                    'type' => 'post_like',
                    'title' => 'Ai đó đã thích bài viết của bạn',
                    'body' => $userName . ' đã thích bài viết của bạn trên cộng đồng.',
                    'reference_type' => 'venue_posts',
                    'reference_id' => $post->id,
                    'data' => isset($post->slug) ? ['slug' => $post->slug] : null,
                    'is_read' => false,
                ]);
            }
            
            return response()->json(['message' => 'Đã thích bài viết.']);
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

        $likesAvailable = Schema::hasTable('community_post_likes');
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
    ): array
    {
        $data = $post->toArray();
        $data['feed_type'] = 'venue_post';
        $data['entity_id'] = $post->id;
        $data['likes_available'] = $likesAvailable;
        $data['is_liked'] = isset($likedLookup[(string) $post->id]);
        $data['author_badges'] = $authorBadges;

        return $data;
    }

    private function normalizeCommunityPost(
        CommunityPost $post,
        bool $likesAvailable,
        array $likedLookup,
        array $authorBadges = []
    ): array
    {
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
        $data['likes_available'] = $likesAvailable;
        $data['is_liked'] = isset($likedLookup[(string) $post->id]);
        $data['author_badges'] = $authorBadges;

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
        $manager = ImageManager::usingDriver(new Driver);
        $image = $manager->decodePath($thumbnail->getPathname());
        $filename = uniqid('community_', true).'.webp';
        $path = 'community_posts/'.$filename;

        if (! Storage::disk('public')->exists('community_posts')) {
            Storage::disk('public')->makeDirectory('community_posts');
        }

        $image->save(storage_path('app/public/'.$path), 80);
        $post->media()->create([
            'collection' => 'thumbnail',
            'file_name' => pathinfo($thumbnail->getClientOriginalName(), PATHINFO_FILENAME).'.webp',
            'file_path' => $path,
            'mime_type' => 'image/webp',
            'file_size' => filesize(storage_path('app/public/'.$path)),
        ]);
    }
}
