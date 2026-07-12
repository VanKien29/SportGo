<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenuePost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerVenuePostGalleryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private VenueCluster $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create([
            'name' => 'venue_owner',
            'display_name' => 'Venue owner',
            'is_system' => true,
        ]);

        $this->owner = User::query()->create([
            'username' => 'gallery_owner',
            'full_name' => 'Gallery Owner',
            'email' => 'gallery-owner@example.com',
            'phone' => '0900000001',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        UserRole::query()->create([
            'user_id' => $this->owner->id,
            'role_id' => $role->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $this->cluster = VenueCluster::query()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Gallery Venue',
            'slug' => 'gallery-venue',
            'address' => 'Ha Noi',
            'latitude' => 21.0285,
            'longitude' => 105.8542,
            'status' => 'active',
        ]);
    }

    public function test_owner_can_create_a_post_with_gallery_images(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->owner, 'sanctum')->post('/api/owner/venue-posts', [
            ...$this->validPayload(),
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'gallery' => [
                UploadedFile::fake()->image('gallery-1.jpg'),
                UploadedFile::fake()->image('gallery-2.png'),
            ],
        ]);

        $response->assertCreated();

        $post = VenuePost::query()->firstOrFail();
        $this->assertSame(2, $post->media()->where('collection', 'gallery')->count());
        $this->assertSame(1, $post->media()->where('collection', 'thumbnail')->count());
    }

    public function test_owner_cannot_create_a_post_with_more_than_ten_gallery_images(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->post('/api/owner/venue-posts', [
            ...$this->validPayload(),
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg'),
            'gallery' => array_map(
                fn (int $index) => UploadedFile::fake()->image("gallery-{$index}.jpg"),
                range(1, 11),
            ),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('gallery');
    }

    public function test_owner_can_replace_a_gallery_image_when_editing_a_post(): void
    {
        Storage::fake('public');
        $post = $this->createDraftPost();
        $removedMedia = $this->createGalleryMedia($post, 'venue_posts/old-gallery.webp');

        $response = $this->actingAs($this->owner, 'sanctum')->post("/api/owner/venue-posts/{$post->id}", [
            '_method' => 'PUT',
            'removed_gallery_media_ids' => [$removedMedia->id],
            'gallery' => [UploadedFile::fake()->image('replacement.jpg')],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $removedMedia->id]);
        $this->assertSame(1, $post->fresh()->media()->where('collection', 'gallery')->count());
    }

    public function test_owner_cannot_remove_gallery_media_from_another_post(): void
    {
        $post = $this->createDraftPost();
        $otherPost = $this->createDraftPost();
        $foreignMedia = $this->createGalleryMedia($otherPost, 'venue_posts/foreign-gallery.webp');

        $response = $this->actingAs($this->owner, 'sanctum')->post("/api/owner/venue-posts/{$post->id}", [
            '_method' => 'PUT',
            'removed_gallery_media_ids' => [$foreignMedia->id],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('removed_gallery_media_ids.0');
        $this->assertDatabaseHas('media', ['id' => $foreignMedia->id]);
    }

    private function validPayload(): array
    {
        return [
            'venue_cluster_id' => $this->cluster->id,
            'title' => 'Thong bao giai dau he',
            'short_description' => 'Thong bao chi tiet ve giai dau mua he.',
            'content' => 'Noi dung bai viet du dai de vuot qua kiem tra toi thieu hai muoi ky tu.',
            'post_type' => 'tournament',
        ];
    }

    private function createDraftPost(): VenuePost
    {
        return VenuePost::query()->create([
            ...$this->validPayload(),
            'author_id' => $this->owner->id,
            'slug' => 'gallery-post-' . fake()->uuid(),
            'status' => 'draft',
        ]);
    }

    private function createGalleryMedia(VenuePost $post, string $path): Media
    {
        Storage::disk('public')->put($path, 'gallery-image');

        return $post->media()->create([
            'collection' => 'gallery',
            'file_name' => basename($path),
            'file_path' => $path,
            'mime_type' => 'image/webp',
            'file_size' => 13,
        ]);
    }
}
