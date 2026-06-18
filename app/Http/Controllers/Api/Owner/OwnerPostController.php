<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use App\Models\Media;
use App\Models\Hashtag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class OwnerPostController extends Controller
{
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
     * Display a listing of the venue posts.
     */
    public function index(Request $request): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $posts = VenuePost::query()
            ->with(['venueCluster:id,name', 'media', 'hashtags'])
            ->whereIn('venue_cluster_id', $clusterIds)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('venue_cluster_id'), function ($query) use ($request, $clusterIds) {
                if ($clusterIds->contains($request->query('venue_cluster_id'))) {
                    $query->where('venue_cluster_id', $request->query('venue_cluster_id'));
                }
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $posts,
        ]);
    }

    /**
     * Store a newly created venue post.
     */
    public function store(Request $request): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'in:' . $clusterIds->implode(',')],
            'content' => ['required', 'string', 'max:20000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5MB per image
        ], [
            'venue_cluster_id.in' => 'Bạn không có quyền đăng bài cho cụm sân này.',
            'content.required' => 'Nội dung bài viết không được để trống.',
        ]);

        // Determine if moderation is required
        $requireModeration = true;
        $config = \App\Models\ModerationConfig::where('key', 'require_venue_post_moderation')->first();
        if ($config) {
            $requireModeration = filter_var($config->value, FILTER_VALIDATE_BOOLEAN);
        }

        $post = DB::transaction(function () use ($request, $data, $requireModeration) {
            $post = VenuePost::create([
                'venue_cluster_id' => $data['venue_cluster_id'],
                'author_id' => $request->user()->id,
                'content' => $data['content'],
                'status' => $requireModeration ? 'pending_review' : 'published',
                'view_count' => 0,
                'like_count' => 0,
                'comment_count' => 0,
            ]);

            // Save images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('posts', 'public');

                    Media::create([
                        'id' => (string) Str::uuid(),
                        'mediable_type' => VenuePost::class,
                        'mediable_id' => $post->id,
                        'collection' => 'venue_post_images',
                        'file_name' => $image->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $image->getClientMimeType(),
                        'file_size' => $image->getSize(),
                        'sort_order' => $index,
                    ]);
                }
            }

            // Parse and sync hashtags
            $this->syncHashtags($post, $data['content']);

            return $post;
        });

        $post = VenuePost::with(['venueCluster:id,name', 'media', 'hashtags'])->findOrFail($post->id);

        return response()->json([
            'status' => 'success',
            'message' => $requireModeration ? 'Đăng bài thành công, bài viết đang chờ phê duyệt.' : 'Đăng bài viết thành công.',
            'data' => $post,
        ], 211); // Using 201 Created status
    }

    /**
     * Display the specified venue post.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $post = VenuePost::with(['venueCluster:id,name', 'media', 'hashtags'])
            ->whereIn('venue_cluster_id', $clusterIds)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $post,
        ]);
    }

    /**
     * Update the specified venue post.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $post = VenuePost::whereIn('venue_cluster_id', $clusterIds)->findOrFail($id);

        if ($post->status === 'hidden') {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài viết đã bị quản trị viên khóa, không thể chỉnh sửa.',
            ], 403);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'deleted_media_ids' => ['nullable', 'array'],
            'deleted_media_ids.*' => ['string', 'exists:media,id'],
        ], [
            'content.required' => 'Nội dung bài viết không được để trống.',
        ]);

        // Determine if moderation is required
        $requireModeration = true;
        $config = \App\Models\ModerationConfig::where('key', 'require_venue_post_moderation')->first();
        if ($config) {
            $requireModeration = filter_var($config->value, FILTER_VALIDATE_BOOLEAN);
        }

        DB::transaction(function () use ($post, $request, $data, $requireModeration) {
            $post->content = $data['content'];
            $post->status = $requireModeration ? 'pending_review' : 'published';
            $post->status_reason = null; // Clear rejection reason upon edit
            $post->save();

            // Delete selected media
            if (!empty($data['deleted_media_ids'])) {
                $medias = Media::where('mediable_type', VenuePost::class)
                    ->where('mediable_id', $post->id)
                    ->whereIn('id', $data['deleted_media_ids'])
                    ->get();

                foreach ($medias as $media) {
                    Storage::disk('public')->delete($media->file_path);
                    $media->delete();
                }
            }

            // Save new images
            if ($request->hasFile('images')) {
                // Get highest current sort order
                $maxSortOrder = Media::where('mediable_type', VenuePost::class)
                    ->where('mediable_id', $post->id)
                    ->max('sort_order') ?? -1;

                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('posts', 'public');

                    Media::create([
                        'id' => (string) Str::uuid(),
                        'mediable_type' => VenuePost::class,
                        'mediable_id' => $post->id,
                        'collection' => 'venue_post_images',
                        'file_name' => $image->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $image->getClientMimeType(),
                        'file_size' => $image->getSize(),
                        'sort_order' => $maxSortOrder + 1 + $index,
                    ]);
                }
            }

            // Sync hashtags
            $this->syncHashtags($post, $data['content']);
        });

        $post = VenuePost::with(['venueCluster:id,name', 'media', 'hashtags'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => $requireModeration ? 'Cập nhật bài viết thành công, bài viết đang chờ phê duyệt lại.' : 'Cập nhật bài viết thành công.',
            'data' => $post,
        ]);
    }

    /**
     * Remove the specified venue post from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $clusterIds = $this->getOwnerClusterIds($request);

        $post = VenuePost::whereIn('venue_cluster_id', $clusterIds)->findOrFail($id);

        if ($post->status === 'hidden') {
            return response()->json([
                'status' => 'error',
                'message' => 'Bài viết đã bị quản trị viên khóa, không thể xóa.',
            ], 403);
        }

        DB::transaction(function () use ($post) {
            // Delete related media
            $medias = Media::where('mediable_type', VenuePost::class)
                ->where('mediable_id', $post->id)
                ->get();

            foreach ($medias as $media) {
                Storage::disk('public')->delete($media->file_path);
                $media->delete();
            }

            // Detach hashtags
            $post->hashtags()->detach();

            // Delete post
            $post->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa bài viết thành công.',
        ]);
    }

    /**
     * Helper to parse and sync hashtags from content.
     */
    private function syncHashtags(VenuePost $post, string $content): void
    {
        preg_match_all('/#(\w+)/u', $content, $matches);
        $hashtagNames = array_unique($matches[1] ?? []);

        $hashtagIds = [];
        foreach ($hashtagNames as $name) {
            $slug = Str::slug($name);
            if (empty($slug)) {
                continue;
            }
            $hashtag = Hashtag::firstOrCreate(
                ['slug' => $slug],
                ['name' => mb_strtolower($name)]
            );
            $hashtagIds[] = $hashtag->id;
        }

        $syncData = [];
        foreach ($hashtagIds as $id) {
            $syncData[$id] = [
                'post_type' => 'venue_posts',
                'created_at' => now(),
            ];
        }

        $post->hashtags()->sync($syncData);
    }
}
