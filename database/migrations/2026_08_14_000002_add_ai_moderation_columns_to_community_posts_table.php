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
                if (! Schema::hasColumn('community_posts', 'ai_verdict')) {
                    $table->string('ai_verdict', 30)->nullable()->default(null)->after('status_reason');
                }
                if (! Schema::hasColumn('community_posts', 'ai_score')) {
                    $table->unsignedTinyInteger('ai_score')->nullable()->default(null)->after('ai_verdict');
                }
                if (! Schema::hasColumn('community_posts', 'ai_summary')) {
                    $table->string('ai_summary', 500)->nullable()->default(null)->after('ai_score');
                }
                if (! Schema::hasColumn('community_posts', 'ai_flags')) {
                    $table->json('ai_flags')->nullable()->after('ai_summary');
                }
                if (! Schema::hasColumn('community_posts', 'ai_reviewed_at')) {
                    $table->timestamp('ai_reviewed_at')->nullable()->default(null)->after('ai_flags');
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
                $columns = ['ai_verdict', 'ai_score', 'ai_summary', 'ai_flags', 'ai_reviewed_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('community_posts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
