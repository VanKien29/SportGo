<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_clusters', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('owner_id', 36)->comment('Chủ sân sở hữu cụm này, trỏ users.id.');
            $table->string('name', 255)->comment('Tên cụm sân hiển thị cho user.');
            $table->string('slug', 255)->unique()->comment('Định danh URL/SEO duy nhất của cụm sân.');
            $table->text('description')->nullable()->comment('Mô tả cụm sân, tiện ích hoặc ghi chú.');
            $table->string('phone_contact', 20)->nullable()->comment('Số điện thoại liên hệ của cụm sân.');
            $table->text('address')->comment('Địa chỉ sân để hiển thị và mở Google Maps.');
            $table->string('map_url', 1000)->nullable()->comment('Link Google Maps lưu lại từ form đăng ký/quản lý sân.');
            $table->decimal('latitude', 10, 7)->comment('Vĩ độ dùng để tìm sân gần vị trí hiện tại.');
            $table->decimal('longitude', 10, 7)->comment('Kinh độ dùng để tìm sân gần vị trí hiện tại.');
            $table->json('amenities')->nullable()->comment('JSON danh sách tiện ích như bãi xe, đèn, phòng tắm.');
            $table->enum('status', ['pending', 'active', 'locked'])->default('pending')->comment('Trạng thái cụm: pending chờ duyệt, active hoạt động, locked bị khóa.');
            $table->text('status_reason')->nullable()->comment('Lý do khóa cụm sân để chủ sân biết.');
            $table->timestamp('locked_at')->nullable()->comment('Thời điểm cụm sân bị khóa.');
            $table->timestamp('locked_until')->nullable()->comment('Thời điểm hết khóa tạm thời của cụm sân.');
            $table->char('locked_by', 36)->nullable()->comment('Admin/nhân viên khóa cụm sân.');
            $table->decimal('rating_avg', 3, 2)->default(0.00)->comment('Điểm trung bình sân, tính từ reviews.');
            $table->unsignedInteger('rating_count')->default(0)->comment('Số lượt review sân.');
            $table->timestamps();
            $table->index(['latitude', 'longitude'], 'venue_clusters_latitude_longitude_index');
            $table->index(['status', 'rating_avg'], 'venue_clusters_status_rating_avg_index');
            $table->index('name', 'venue_clusters_name_index');
            $table->index('status', 'venue_clusters_status_index');
            $table->index('rating_avg', 'venue_clusters_rating_avg_index');
            $table->index('locked_until', 'venue_clusters_locked_until_index');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_clusters');
    }
};
