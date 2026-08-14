<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('community_posts', 'deleted_at')) {
                $table->softDeletes()->after('status_reason');
            }
            if (! Schema::hasColumn('community_posts', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('community_posts', 'edit_count')) {
                $table->unsignedInteger('edit_count')->default(0)->after('edited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            if (Schema::hasColumn('community_posts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('community_posts', 'edited_at')) {
                $table->dropColumn('edited_at');
            }
            if (Schema::hasColumn('community_posts', 'edit_count')) {
                $table->dropColumn('edit_count');
            }
        });
    }
};
