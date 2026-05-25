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
        Schema::create('media', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('mediable_type', 100)->comment('Loại đối tượng sở hữu file như users, venue_clusters, reports; polymorphic, không FK vật lý.; VD: booking_reminder');
            $table->string('mediable_id', 100)->comment('ID đối tượng sở hữu file; validate bằng Laravel service theo mediable_type.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('collection', 50)->comment('Nhóm file theo nghiệp vụ như avatar, gallery, proof, evidence, attachments.; VD: giá trị mẫu');
            $table->string('file_name', 255)->comment('Tên file hiển thị/gốc.; VD: Sân Cầu Lông A1');
            $table->string('file_path', 500)->comment('Đường dẫn hoặc storage key của file trong storage/cloud.; VD: uploads/demo/san-a1.jpg');
            $table->string('mime_type', 100)->comment('Loại file để validate ảnh/pdf và render preview.; VD: booking_reminder');
            $table->bigInteger('file_size')->comment('Dung lượng file tính bằng byte để kiểm tra giới hạn upload.; VD: uploads/demo/san-a1.jpg');
            $table->smallInteger('sort_order')->comment('Thứ tự hiển thị nếu một đối tượng có nhiều file.; VD: 1');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm upload file.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['mediable_type', 'mediable_id', 'collection'], 'media_mediable_collection_index');
            $table->index(['mediable_type', 'mediable_id'], 'media_mediable_type_mediable_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
