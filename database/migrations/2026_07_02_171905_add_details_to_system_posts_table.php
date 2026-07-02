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
        Schema::table('system_posts', function (Blueprint $table) {
            $table->string('short_description', 500)->nullable()->after('title')->comment('Tóm tắt bài viết');
            $table->string('category', 255)->nullable()->after('short_description')->comment('Danh mục bài viết');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_posts', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'category']);
        });
    }
};
