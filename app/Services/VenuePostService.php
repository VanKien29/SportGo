<?php

namespace App\Services;

use App\Models\VenuePost;
use App\Models\AuditLog;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VenuePostService
{
    /**
     * @param array $data
     * @param \App\Models\User $user
     * @param UploadedFile $thumbnail
     * @return VenuePost
     */
    public function createPost(array $data, $user, ?UploadedFile $thumbnail = null)
    {
        return DB::transaction(function () use ($data, $user, $thumbnail) {
            $slug = Str::slug($data['title']);
            $count = VenuePost::where('slug', 'LIKE', "{$slug}%")->count();
            if ($count > 0) {
                $slug = $slug . '-' . ($count + 1);
            }

            // Auto approve logic
            $status = !empty($data['is_draft']) ? 'draft' : 'pending_review';
            if ($status === 'pending_review') {
                $isCommunityType = in_array($data['post_type'], ['news', 'event', 'promotion', 'announcement']);
                $configKey = $isCommunityType ? 'require_community_post_moderation' : 'require_venue_post_moderation';
                $requireModeration = \App\Models\ModerationConfig::where('key', $configKey)->value('value');
                
                // If the config explicitly says 'false' (do not require moderation)
                if ($requireModeration === 'false' || $requireModeration === false) {
                    $status = 'published';
                }
            }

            $post = VenuePost::create([
                'venue_cluster_id' => $data['venue_cluster_id'] ?? null,
                'author_id' => $user->id,
                'title' => $data['title'],
                'slug' => $slug,
                'content' => $data['content'],
                'short_description' => $data['short_description'],
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'post_type' => $data['post_type'],
                'status' => $status,
            ]);

            // Save tags
            if (isset($data['tags'])) {
                $hashtagIds = [];
                foreach ($data['tags'] as $tagName) {
                    $tagName = trim($tagName);
                    if ($tagName !== '') {
                        $hashtag = \App\Models\Hashtag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => Str::slug($tagName)]
                        );
                        $hashtagIds[] = $hashtag->id;
                    }
                }
                $post->hashtags()->syncWithPivotValues($hashtagIds, ['post_type' => 'venue_posts']);
            }

            // Save thumbnail
            if ($thumbnail) {
                $extension = strtolower($thumbnail->getClientOriginalExtension() ?: 'webp');
                $filename = uniqid('thumb_', true) . '.' . $extension;
                $path = $thumbnail->storeAs('venue_posts', $filename, 'public');

                $post->media()->create([
                    'collection' => 'thumbnail',
                    'file_name' => $thumbnail->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $thumbnail->getClientMimeType() ?: 'image/' . $extension,
                    'file_size' => $thumbnail->getSize() ?: 0,
                ]);
            }

            $this->storeGalleryFiles($post, $data['gallery'] ?? []);

            $this->logAction($user->id, 'venue_post.created', $post, null, $post->toArray(), 'Tạo bài viết mới');

            return $post;
        });
    }

    public function updatePost(VenuePost $post, array $data, $user, ?UploadedFile $thumbnail)
    {
        // Enforce: Pending posts cannot be edited
        if ($post->status === 'pending_review' && !$this->userHasRole($user, ['admin', 'super_admin'])) {
            throw new \InvalidArgumentException('Không thể chỉnh sửa bài viết đang trong trạng thái chờ duyệt.');
        }

        return DB::transaction(function () use ($post, $data, $user, $thumbnail) {
            $oldValues = $post->toArray();

            if (isset($data['title']) && $data['title'] !== $post->title) {
                $slug = Str::slug($data['title']);
                $count = VenuePost::where('slug', 'LIKE', "{$slug}%")->where('id', '!=', $post->id)->count();
                if ($count > 0) {
                    $slug = $slug . '-' . ($count + 1);
                }
                $post->slug = $slug;
            }

            $post->fill($data);

            // Reversion and status transitions logic
            if (!empty($data['is_draft'])) {
                $this->validateStatusTransition($oldValues['status'], 'draft');
                $post->status = 'draft';
            } elseif (!$this->userHasRole($user, ['admin', 'super_admin'])) {
                // If the user is an owner and they are submitting/editing a non-draft post
                if (in_array($oldValues['status'], ['published', 'rejected', 'hidden', 'draft'])) {
                    // Any edit to these states reverts to pending_review for admin check
                    if ($oldValues['status'] !== 'pending_review') {
                        $this->validateStatusTransition($oldValues['status'], 'pending_review');
                        $post->status = 'pending_review';
                    }
                }
            }

            $post->save();

            // Save tags
            if (isset($data['tags'])) {
                $hashtagIds = [];
                foreach ($data['tags'] as $tagName) {
                    $tagName = trim($tagName);
                    if ($tagName !== '') {
                        $hashtag = \App\Models\Hashtag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => Str::slug($tagName)]
                        );
                        $hashtagIds[] = $hashtag->id;
                    }
                }
                $post->hashtags()->syncWithPivotValues($hashtagIds, ['post_type' => 'venue_posts']);
            }

            if ($thumbnail) {
                // Remove old
                $post->media()->where('collection', 'thumbnail')->delete();
                
                $extension = strtolower($thumbnail->getClientOriginalExtension() ?: 'webp');
                $filename = uniqid('thumb_', true) . '.' . $extension;
                $path = $thumbnail->storeAs('venue_posts', $filename, 'public');

                $post->media()->create([
                    'collection' => 'thumbnail',
                    'file_name' => $thumbnail->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $thumbnail->getClientMimeType() ?: 'image/' . $extension,
                    'file_size' => $thumbnail->getSize() ?: 0,
                ]);
            }

            $this->syncGallery($post, $data);

            $this->logAction($user->id, 'venue_post.updated', $post, $oldValues, $post->toArray(), 'Cập nhật bài viết');

            return $post;
        });
    }

    public function changeStatus(VenuePost $post, string $status, $user, string $reason = null)
    {
        $oldValues = $post->toArray();
        
        $this->validateStatusTransition($oldValues['status'], $status);

        $post->status = $status;
        if ($reason) {
            $post->status_reason = $reason;
        }
        if (in_array($status, ['published', 'rejected', 'hidden'])) {
            $post->reviewed_by = $user->id;
            $post->reviewed_at = now();
        }
        $post->save();

        $this->logAction($user->id, "venue_post.status_{$status}", $post, $oldValues, $post->toArray(), "Đổi trạng thái thành {$status}");

        return $post;
    }

    public function deletePost(VenuePost $post, $user)
    {
        if ($post->status === 'published') {
            $post->delete();
            $this->logAction($user->id, 'venue_post.deleted', $post, null, null, 'Xóa bài viết (soft delete)');
        } else {
            $post->forceDelete();
            $this->logAction($user->id, 'venue_post.force_deleted', $post, null, null, 'Xóa bài viết vĩnh viễn');
        }
    }

    public function restorePost(VenuePost $post, $user)
    {
        $post->restore();
        $this->logAction($user->id, 'venue_post.restored', $post, null, null, 'Khôi phục bài viết');
    }

    private function syncGallery(VenuePost $post, array $data): void
    {
        $removedIds = array_values(array_unique($data['removed_gallery_media_ids'] ?? []));
        $gallery = $post->media()->where('collection', 'gallery');

        if ($removedIds !== []) {
            $mediaToRemove = (clone $gallery)->whereIn('id', $removedIds)->get();

            if ($mediaToRemove->count() !== count($removedIds)) {
                throw ValidationException::withMessages([
                    'removed_gallery_media_ids.0' => ['Ảnh cần xóa không thuộc bài viết này.'],
                ]);
            }

            foreach ($mediaToRemove as $media) {
                Storage::disk('public')->delete($media->getRawOriginal('file_path'));
                $media->delete();
            }
        }

        $this->ensureGalleryLimit($post, count($data['gallery'] ?? []));
        $this->storeGalleryFiles($post, $data['gallery'] ?? []);
    }

    private function ensureGalleryLimit(VenuePost $post, int $newFileCount): void
    {
        $galleryCount = $post->media()->where('collection', 'gallery')->count();

        if ($galleryCount + $newFileCount > 10) {
            throw ValidationException::withMessages([
                'gallery' => ['Mỗi bài viết chỉ được có tối đa 10 ảnh.'],
            ]);
        }
    }

    private function storeGalleryFiles(VenuePost $post, array $files): void
    {
        $this->ensureGalleryLimit($post, count($files));

        $nextSortOrder = ((int) $post->media()
            ->where('collection', 'gallery')
            ->max('sort_order')) + 1;

        foreach ($files as $file) {
            $this->storeGalleryFile($post, $file, $nextSortOrder);
            $nextSortOrder++;
        }
    }

    private function storeGalleryFile(VenuePost $post, UploadedFile $file, int $sortOrder): void
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'webp');
        $filename = uniqid('gallery_', true) . '.' . $extension;
        $path = $file->storeAs('venue_posts', $filename, 'public');

        $post->media()->create([
            'collection' => 'gallery',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType() ?: 'image/' . $extension,
            'file_size' => $file->getSize() ?: 0,
            'sort_order' => $sortOrder,
        ]);
    }

    private function validateStatusTransition(string $from, string $to): void
    {
        $allowed = [
            'draft' => ['pending_review', 'draft'],
            'pending_review' => ['published', 'rejected', 'draft', 'pending_review'],
            'published' => ['hidden', 'pending_review'],
            'rejected' => ['draft', 'pending_review'],
            'hidden' => ['published', 'pending_review'],
        ];

        if ($from === $to) {
            return;
        }

        if (!isset($allowed[$from]) || !in_array($to, $allowed[$from])) {
            $fromLabel = $this->statusLabel($from);
            $toLabel = $this->statusLabel($to);
            throw new \InvalidArgumentException("Không thể chuyển trạng thái bài viết từ '{$fromLabel}' sang '{$toLabel}'.");
        }
    }

    private function userHasRole($user, array $roles): bool
    {
        return $user->roles()
            ->whereIn('roles.name', $roles)
            ->exists();
    }

    private function statusLabel(string $status): string
    {
        $map = [
            'draft' => 'Bản nháp',
            'pending_review' => 'Chờ duyệt',
            'published' => 'Đã xuất bản',
            'rejected' => 'Từ chối',
            'hidden' => 'Đã ẩn',
        ];
        return $map[$status] ?? $status;
    }

    private function logAction($actorId, $action, $entity, $oldValues, $newValues, $reason = null)
    {
        AuditLog::create([
            'actor_id' => $actorId,
            'actor_type' => 'owner',
            'action' => $action,
            'module' => 'venue_posts',
            'entity_type' => 'venue_posts',
            'entity_id' => $entity->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
        ]);
    }
}
