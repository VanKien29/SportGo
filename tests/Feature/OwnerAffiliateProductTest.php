<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\AffiliateProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerAffiliateProductTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private VenueCluster $cluster;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create Roles
        $ownerRole = Role::create(['name' => 'venue_owner', 'display_name' => 'Owner', 'is_system' => true]);

        // Create Owner User
        $this->owner = User::create([
            'username' => 'owner_test',
            'full_name' => 'Owner Test',
            'email' => 'owner@sportgo.vn',
            'phone' => '0999999992',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => $ownerRole->id,
            'scope_type' => 'system',
            'scope_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        // Create Venue Cluster
        $this->cluster = VenueCluster::create([
            'owner_id' => $this->owner->id,
            'name' => 'Affiliate Test Cluster',
            'slug' => 'affiliate-test-cluster',
            'address' => 'Hanoi',
            'latitude' => 21.0285,
            'longitude' => 105.8542,
            'status' => 'active',
        ]);
    }

    /**
     * Storing a product without an image should fail validation.
     */
    public function test_store_product_requires_image(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster->id}/affiliate-products", [
                'name' => 'Test Product',
                'affiliate_url' => 'https://shopee.vn/product',
                'platform_name' => 'Shopee',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    /**
     * Storing a product with JPG/PNG image should convert it to WebP.
     */
    public function test_store_product_converts_jpg_png_to_webp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        // 1. Create a fake uploaded file (PNG)
        $pngFile = UploadedFile::fake()->image('product.png', 100, 100);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster->id}/affiliate-products", [
                'name' => 'Test PNG Product',
                'affiliate_url' => 'https://shopee.vn/product-png',
                'platform_name' => 'Shopee',
                'image' => $pngFile,
            ]);

        $response->assertStatus(201);

        $product = AffiliateProduct::first();
        $this->assertNotNull($product);
        $this->assertNotNull($product->image_path);
        
        // Assert the stored filename ends with .webp
        $this->assertStringEndsWith('.webp', $product->image_path);

        // Verify file exists on public disk
        Storage::disk('public')->assertExists($product->image_path);
    }

    /**
     * Storing a product with WEBP image should save it directly.
     */
    public function test_store_product_saves_webp_directly(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $webpFile = UploadedFile::fake()->image('product.webp', 100, 100);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/owner/venue-clusters/{$this->cluster->id}/affiliate-products", [
                'name' => 'Test WebP Product',
                'affiliate_url' => 'https://shopee.vn/product-webp',
                'platform_name' => 'Shopee',
                'image' => $webpFile,
            ]);

        $response->assertStatus(201);

        $product = AffiliateProduct::first();
        $this->assertNotNull($product);
        $this->assertStringEndsWith('.webp', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    /**
     * Updating a product with a new image should convert it and remove the old one.
     */
    public function test_update_product_converts_and_cleans_old_image(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $oldFile = UploadedFile::fake()->image('old.webp', 100, 100);
        $product = AffiliateProduct::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'venue_cluster_id' => $this->cluster->id,
            'name' => 'Original Product',
            'affiliate_url' => 'https://shopee.vn/original',
            'image_path' => $oldFile->store('products', 'public'),
        ]);

        Storage::disk('public')->assertExists($product->image_path);

        $newJpgFile = UploadedFile::fake()->image('new.jpg', 150, 150);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/affiliate-products/{$product->id}", [
                'name' => 'Updated Product Name',
                'affiliate_url' => 'https://shopee.vn/updated',
                'image' => $newJpgFile,
            ]);

        $response->assertStatus(200);

        // Assert old file was deleted
        Storage::disk('public')->assertMissing($product->image_path);

        $product->refresh();
        $this->assertEquals('Updated Product Name', $product->name);
        $this->assertStringEndsWith('.webp', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    /**
     * Updating a product without a new image should succeed and keep the old image.
     */
    public function test_update_product_without_image_keeps_old_image(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $file = UploadedFile::fake()->image('image.webp', 100, 100);
        $product = AffiliateProduct::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'venue_cluster_id' => $this->cluster->id,
            'name' => 'Original Product',
            'affiliate_url' => 'https://shopee.vn/original',
            'image_path' => $file->store('products', 'public'),
        ]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/owner/affiliate-products/{$product->id}", [
                'name' => 'Updated Name Only',
                'affiliate_url' => 'https://shopee.vn/original',
            ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals('Updated Name Only', $product->name);
        Storage::disk('public')->assertExists($product->image_path);
    }
}
