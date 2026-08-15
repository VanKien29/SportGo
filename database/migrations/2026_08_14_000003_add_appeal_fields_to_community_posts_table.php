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
        if (Schema::hasTable('community_posts')) {
            Schema::table('community_posts', function (Blueprint $table) {
                if (! Schema::hasColumn('community_posts', 'appeal_note')) {
                    $table->text('appeal_note')->nullable()->default(null)->after('status_reason');
                }
                if (! Schema::hasColumn('community_posts', 'appealed_at')) {
                    $table->timestamp('appealed_at')->nullable()->default(null)->after('appeal_note');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('community_posts')) {
            Schema::table('community_posts', function (Blueprint $table) {
                $columns = ['appeal_note', 'appealed_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('community_posts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
