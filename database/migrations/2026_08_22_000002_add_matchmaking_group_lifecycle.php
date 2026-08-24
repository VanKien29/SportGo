<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversation_participants') && ! Schema::hasColumn('conversation_participants', 'left_at')) {
            Schema::table('conversation_participants', function (Blueprint $table): void {
                $table->timestamp('left_at')->nullable()->after('joined_at');
                $table->index(['conversation_id', 'left_at'], 'conversation_participants_conversation_left_index');
            });
        }

        if (Schema::hasTable('player_post_participants') && ! Schema::hasColumn('player_post_participants', 'left_at')) {
            Schema::table('player_post_participants', function (Blueprint $table): void {
                $table->timestamp('left_at')->nullable()->after('responded_at');
                $table->index(['post_id', 'status', 'left_at'], 'player_post_participants_post_status_left_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversation_participants') && Schema::hasColumn('conversation_participants', 'left_at')) {
            Schema::table('conversation_participants', function (Blueprint $table): void {
                $table->dropIndex('conversation_participants_conversation_left_index');
                $table->dropColumn('left_at');
            });
        }

        if (Schema::hasTable('player_post_participants') && Schema::hasColumn('player_post_participants', 'left_at')) {
            Schema::table('player_post_participants', function (Blueprint $table): void {
                $table->dropIndex('player_post_participants_post_status_left_index');
                $table->dropColumn('left_at');
            });
        }
    }
};
