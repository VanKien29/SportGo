<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCommunityPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_community_post_can_be_opened_by_its_public_slug(): void
    {
        $author = User::factory()->create();
        $post = CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'Bài chia sẻ cộng đồng dùng để kiểm tra trang chi tiết.',
            'status' => 'published',
            'view_count' => 0,
            'like_count' => 0,
            'comment_count' => 0,
        ]);

        $response = $this->getJson("/api/venue-posts/community-{$post->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', "community-{$post->id}")
            ->assertJsonPath('data.entity_id', $post->id)
            ->assertJsonPath('data.feed_type', 'community_post')
            ->assertJsonPath('data.author.id', $author->id);

        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'view_count' => 1,
        ]);
    }

    public function test_unpublished_community_post_is_not_publicly_readable(): void
    {
        $author = User::factory()->create();
        $post = CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'Bài viết đang chờ kiểm duyệt không được hiển thị công khai.',
            'status' => 'pending_review',
        ]);

        $this->getJson("/api/venue-posts/community-{$post->id}")
            ->assertForbidden();
    }
}
