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
            $table->string('ai_verdict', 32)->nullable()->after('status_reason');
            $table->unsignedTinyInteger('ai_score')->nullable()->after('ai_verdict');
            $table->text('ai_summary')->nullable()->after('ai_score');
            $table->json('ai_flags')->nullable()->after('ai_summary');
            $table->timestamp('ai_reviewed_at')->nullable()->after('ai_flags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_posts', function (Blueprint $table) {
            $table->dropColumn([
                'ai_verdict',
                'ai_score',
                'ai_summary',
                'ai_flags',
                'ai_reviewed_at',
            ]);
        });
    }
};
