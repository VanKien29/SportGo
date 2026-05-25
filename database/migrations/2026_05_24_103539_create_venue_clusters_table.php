<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('venue_clusters', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('owner_id', 36)->comment('Chủ sân sở hữu cụm này, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('name', 255)->comment('Tên cụm sân hiển thị cho user.; VD: Sân Cầu Lông A1');
            $table->string('slug', 255)->unique()->comment('Định danh URL/SEO duy nhất của cụm sân.; VD: san-cau-long-a1');
            $table->text('description')->nullable()->comment('Mô tả cụm sân, tiện ích hoặc ghi chú.; VD: Nội dung mẫu dùng để demo.');
            $table->string('phone_contact', 20)->nullable()->comment('Số điện thoại liên hệ của cụm sân.; VD: 0987654321');
            $table->text('address')->comment('Địa chỉ sân để hiển thị và mở Google Maps.; VD: 123 Nguyễn Trãi, Hà Nội');
            $table->string('map_url', 1000)->nullable()->comment('Link Google Maps lưu lại từ form đăng ký/quản lý sân.; VD: uploads/demo/san-a1.jpg');
            $table->decimal('latitude', 10, 7)->comment('Vĩ độ dùng để tìm sân gần vị trí hiện tại.; VD: 21.027800');
            $table->decimal('longitude', 10, 7)->comment('Kinh độ dùng để tìm sân gần vị trí hiện tại.; VD: 105.834200');
            $table->json('amenities')->nullable()->comment('JSON danh sách tiện ích như bãi xe, đèn, phòng tắm.; VD: {"key":"value"}');
            $table->string('status')->comment('Trạng thái cụm: pending chờ duyệt, active hoạt động, locked bị khóa. Giá trị enum: pending=chờ xử lý; active=đang hoạt động; locked=bị khóa.; VD: confirmed');
            $table->text('status_reason')->nullable()->comment('Lý do khóa cụm sân để chủ sân biết.; VD: Nội dung mẫu dùng để demo.');
            $table->timestamp('locked_at')->nullable()->comment('Thời điểm cụm sân bị khóa.; VD: 2026-06-15 18:00:00');
            $table->timestamp('locked_until')->nullable()->comment('Thời điểm hết khóa tạm thời của cụm sân.; VD: true');
            $table->char('locked_by', 36)->nullable()->comment('Admin/nhân viên khóa cụm sân.; VD: true');
            $table->decimal('rating_avg', 3, 2)->comment('Điểm trung bình sân, tính từ reviews.; VD: 60');
            $table->integer('rating_count')->comment('Số lượt review sân.; VD: 60');
            $table->timestamps();
            $table->index(['latitude', 'longitude'], 'venue_clusters_latitude_longitude_index');
            $table->index(['status', 'rating_avg'], 'venue_clusters_status_rating_avg_index');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_clusters');
    }
};
