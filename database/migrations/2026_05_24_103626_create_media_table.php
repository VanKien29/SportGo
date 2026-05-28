<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('mediable_type', 100)->comment('Loại đối tượng sở hữu file; polymorphic.');
            $table->string('mediable_id', 100)->comment('ID đối tượng sở hữu file.');
            $table->string('collection', 50)->default('default')->comment('Nhóm file theo nghiệp vụ.');
            $table->string('file_name', 255)->comment('Tên file hiển thị/gốc.');
            $table->string('file_path', 500)->comment('Đường dẫn hoặc storage key.');
            $table->string('mime_type', 100)->comment('Loại file để validate ảnh/pdf.');
            $table->unsignedBigInteger('file_size')->default(0)->comment('Dung lượng file tính bằng byte.');
            $table->smallInteger('sort_order')->default(0)->comment('Thứ tự hiển thị.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm upload file.');
            $table->index('collection', 'media_collection_index');
            $table->index('mime_type', 'media_mime_type_index');
            $table->index(['mediable_type', 'mediable_id', 'collection'], 'media_mediable_collection_index');
            $table->index(['mediable_type', 'mediable_id'], 'media_mediable_type_mediable_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
