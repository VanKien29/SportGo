<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('venue_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('venue_posts', 'title')) {
                $table->string('title', 255)->nullable()->after('author_id');
            }
            if (!Schema::hasColumn('venue_posts', 'post_type')) {
                $table->enum('post_type', ['promotion', 'tournament', 'news', 'notice', 'recruitment'])->default('news');
            }
            if (!Schema::hasColumn('venue_posts', 'valid_from')) {
                $table->timestamp('valid_from')->nullable();
            }
            if (!Schema::hasColumn('venue_posts', 'valid_to')) {
                $table->timestamp('valid_to')->nullable();
            }
            if (!Schema::hasColumn('venue_posts', 'slug')) {
                $table->string('slug')->unique()->nullable();
            }
        });

        // Update ENUM using raw SQL to be safe across different MariaDB/MySQL versions
        DB::statement("ALTER TABLE venue_posts MODIFY COLUMN status ENUM('draft', 'pending_review', 'published', 'rejected', 'hidden') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE venue_posts MODIFY COLUMN status ENUM('pending_review', 'published', 'rejected', 'hidden') DEFAULT 'pending_review'");

        Schema::table('venue_posts', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('venue_posts', 'slug')) $cols[] = 'slug';
            if (Schema::hasColumn('venue_posts', 'title')) $cols[] = 'title';
            if (Schema::hasColumn('venue_posts', 'post_type')) $cols[] = 'post_type';
            if (Schema::hasColumn('venue_posts', 'valid_from')) $cols[] = 'valid_from';
            if (Schema::hasColumn('venue_posts', 'valid_to')) $cols[] = 'valid_to';
            if (count($cols) > 0) {
                $table->dropColumn($cols);
            }
        });
    }
};
