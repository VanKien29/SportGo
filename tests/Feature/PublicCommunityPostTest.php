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

    public function test_community_feed_filters_content_and_hides_unpublished_posts(): void
    {
        $author = User::factory()->create();
        $matchingPost = CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'Kinh nghiệm tập pickleball cho người mới bắt đầu.',
            'status' => 'published',
        ]);
        CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'Bài cầu lông không khớp từ khóa.',
            'status' => 'published',
        ]);
        CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'pickleball nhưng đang chờ kiểm duyệt.',
            'status' => 'pending_review',
        ]);

        $this->getJson('/api/venue-posts?feed_type=community_post&keyword=pickleball')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', "community-{$matchingPost->id}")
            ->assertJsonPath('data.0.feed_type', 'community_post');
    }

    public function test_authenticated_user_can_create_a_community_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/venue-posts', [
                'title' => 'Một buổi tập đáng nhớ',
                'short_description' => 'Chia sẻ ngắn về một buổi tập thể thao.',
                'content' => 'Hôm nay nhóm mình có một buổi tập rất vui và học thêm nhiều kỹ thuật mới.',
                'post_type' => 'news',
                'is_draft' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.feed_type', 'community_post')
            ->assertJsonPath('data.status', 'pending_review');

        $this->assertDatabaseHas('community_posts', [
            'author_id' => $user->id,
            'status' => 'pending_review',
        ]);
    }

    public function test_authenticated_user_can_comment_and_toggle_like(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $post = CommunityPost::query()->create([
            'author_id' => $author->id,
            'content' => 'Bài viết công khai để kiểm tra bình luận và lượt thích.',
            'status' => 'published',
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/venue-posts/community-{$post->id}/comments", [
                'content' => 'Cảm ơn bạn đã chia sẻ kinh nghiệm này.',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $member->id)
            ->assertJsonPath('data.comment_count', 1);

        $this->assertDatabaseHas('community_post_comments', [
            'post_id' => $post->id,
            'user_id' => $member->id,
            'status' => 'visible',
        ]);
        $this->assertDatabaseHas('community_posts', [
            'id' => $post->id,
            'comment_count' => 1,
        ]);

        $this->postJson("/api/venue-posts/community-{$post->id}/likes")
            ->assertOk()
            ->assertJsonPath('data.is_liked', true)
            ->assertJsonPath('data.like_count', 1);

        $this->getJson('/api/venue-posts?feed_type=community_post')
            ->assertOk()
            ->assertJsonPath('data.0.id', "community-{$post->id}")
            ->assertJsonPath('data.0.like_count', 1)
            ->assertJsonPath('data.0.comment_count', 1);

        $this->assertDatabaseHas('community_post_likes', [
            'post_id' => $post->id,
            'user_id' => $member->id,
        ]);

        $this->postJson("/api/venue-posts/community-{$post->id}/likes")
            ->assertOk()
            ->assertJsonPath('data.is_liked', false)
            ->assertJsonPath('data.like_count', 0);

        $this->assertDatabaseMissing('community_post_likes', [
            'post_id' => $post->id,
            'user_id' => $member->id,
        ]);
    }
}
