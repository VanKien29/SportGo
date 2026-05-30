<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_post_comments')) {
            return;
        }

        Schema::table('community_post_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('community_post_comments', 'reviewed_by')) {
                $table->char('reviewed_by', 36)->nullable()->after('status')->comment('Admin/moderator xử lý bình luận.');
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by')->comment('Thời điểm xử lý bình luận.');
                $table->text('status_reason')->nullable()->after('reviewed_at')->comment('Lý do ẩn/khôi phục bình luận.');
                $table->index('reviewed_by', 'community_post_comments_reviewed_by_index');
                $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('community_post_comments') || ! Schema::hasColumn('community_post_comments', 'reviewed_by')) {
            return;
        }

        Schema::table('community_post_comments', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex('community_post_comments_reviewed_by_index');
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'status_reason']);
        });
    }
};
