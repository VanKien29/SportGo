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
        Schema::table('player_posts', function (Blueprint $table) {
            $table->string('image_path', 255)->nullable()->after('description')->comment('Đường dẫn ảnh đính kèm (tùy chọn)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_posts', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
