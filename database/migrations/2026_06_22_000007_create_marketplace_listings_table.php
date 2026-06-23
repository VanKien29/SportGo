<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('seller_id', 36)->comment('User dang ban/pass do.');
            $table->string('title', 255)->comment('Tieu de tin dang.');
            $table->text('description')->nullable()->comment('Mo ta chi tiet.');
            $table->decimal('price', 12, 2)->comment('Gia de nghi.');
            $table->boolean('is_negotiable')->default(false)->comment('Co the thuong luong.');
            $table->enum('condition', ['new', 'like_new', 'good', 'fair'])->default('good')
                ->comment('Tinh trang san pham.');
            $table->string('category', 100)->comment('Danh muc san pham.');
            $table->unsignedBigInteger('court_type_id')->nullable()->comment('Mon the thao lien quan.');
            $table->char('preferred_venue_cluster_id', 36)->nullable()->comment('Dia diem giao hang uu tien.');
            $table->text('pickup_address')->nullable()->comment('Dia chi lay hang neu khong giao tai san.');
            $table->enum('status', ['draft', 'active', 'sold', 'expired', 'hidden', 'rejected'])->default('draft')
                ->comment('Trang thai tin dang.');
            $table->char('reviewed_by', 36)->nullable()->comment('Admin kiem duyet.');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status'], 'marketplace_listings_seller_status_index');
            $table->index(['status', 'created_at'], 'marketplace_listings_status_created_index');
            $table->index(['category', 'status'], 'marketplace_listings_category_status_index');
            $table->index(['court_type_id', 'status'], 'marketplace_listings_court_type_status_index');
            $table->index('preferred_venue_cluster_id', 'marketplace_listings_preferred_cluster_index');
            $table->index('expires_at', 'marketplace_listings_expires_at_index');
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('set null');
            $table->foreign('preferred_venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
