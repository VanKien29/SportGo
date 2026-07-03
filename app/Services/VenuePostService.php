<?php

namespace App\Services;

use App\Models\VenuePost;
use App\Models\AuditLog;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class VenuePostService
{
    /**
     * @param array $data
     * @param \App\Models\User $user
     * @param UploadedFile $thumbnail
     * @return VenuePost
     */
    public function createPost(array $data, $user, UploadedFile $thumbnail)
    {
        return DB::transaction(function () use ($data, $user, $thumbnail) {
            $slug = Str::slug($data['title']);
            $count = VenuePost::where('slug', 'LIKE', "{$slug}%")->count();
            if ($count > 0) {
                $slug = $slug . '-' . ($count + 1);
            }

            $post = VenuePost::create([
                'venue_cluster_id' => $data['venue_cluster_id'],
                'author_id' => $user->id,
                'title' => $data['title'],
                'slug' => $slug,
                'content' => $data['content'],
                'short_description' => $data['short_description'],
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'post_type' => $data['post_type'],
                'status' => !empty($data['is_draft']) ? 'draft' : 'pending_review',
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

            // Convert and save thumbnail to WebP using Intervention Image
            $manager = new ImageManager(new Driver());
            $image = $manager->decodePath($thumbnail->getPathname());
            
            $filename = uniqid('thumb_', true) . '.webp';
            $path = 'venue_posts/' . $filename;
            
            if (!Storage::disk('public')->exists('venue_posts')) {
                Storage::disk('public')->makeDirectory('venue_posts');
            }
            
            $image->save(storage_path('app/public/' . $path), quality: 80);

            $post->media()->create([
                'collection' => 'thumbnail',
                'file_name' => $thumbnail->getClientOriginalName() . '.webp',
                'file_path' => $path,
                'mime_type' => 'image/webp',
                'file_size' => filesize(storage_path('app/public/' . $path)),
            ]);

            $this->logAction($user->id, 'venue_post.created', $post, null, $post->toArray(), 'Tạo bài viết mới');

            return $post;
        });
    }

    public function updatePost(VenuePost $post, array $data, $user, ?UploadedFile $thumbnail)
    {
        // Enforce: Pending posts cannot be edited
        if ($post->status === 'pending_review' && !$user->hasRole(['admin', 'super_admin'])) {
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
            } elseif (!$user->hasRole(['admin', 'super_admin'])) {
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
                
                // Convert and save thumbnail to WebP using Intervention Image
                $manager = new ImageManager(new Driver());
                $image = $manager->decodePath($thumbnail->getPathname());
                
                $filename = uniqid('thumb_', true) . '.webp';
                $path = 'venue_posts/' . $filename;
                
                if (!Storage::disk('public')->exists('venue_posts')) {
                    Storage::disk('public')->makeDirectory('venue_posts');
                }
                
                $image->save(storage_path('app/public/' . $path), quality: 80);

                $post->media()->create([
                    'collection' => 'thumbnail',
                    'file_name' => $thumbnail->getClientOriginalName() . '.webp',
                    'file_path' => $path,
                    'mime_type' => 'image/webp',
                    'file_size' => filesize(storage_path('app/public/' . $path)),
                ]);
            }

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
