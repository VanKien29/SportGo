<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('player_posts') && ! Schema::hasColumn('player_posts', 'target_players')) {
            Schema::table('player_posts', function (Blueprint $table): void {
                $table->unsignedSmallInteger('target_players')->nullable()->after('needed_players');
            });
        }

        if (Schema::hasTable('player_post_participants')
            && ! Schema::hasColumn('player_post_participants', 'removal_reason')) {
            Schema::table('player_post_participants', function (Blueprint $table): void {
                $table->text('removal_reason')->nullable()->after('left_at');
            });
        }

        if (Schema::hasTable('player_post_participants') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE player_post_participants MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled','expired','left','removed_by_author') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasTable('player_posts') && Schema::hasColumn('player_posts', 'target_players')) {
            DB::table('player_posts')
                ->whereNull('target_players')
                ->orderBy('id')
                ->chunkById(500, function ($posts): void {
                    foreach ($posts as $post) {
                        $approved = DB::table('player_post_participants')
                            ->where('post_id', $post->id)
                            ->where('status', 'approved')
                            ->count();

                        DB::table('player_posts')
                            ->where('id', $post->id)
                            ->update([
                                'target_players' => max(0, (int) $post->needed_players + $approved),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('player_post_participants') && Schema::hasColumn('player_post_participants', 'removal_reason')) {
            Schema::table('player_post_participants', function (Blueprint $table): void {
                $table->dropColumn('removal_reason');
            });
        }

        if (Schema::hasTable('player_posts') && Schema::hasColumn('player_posts', 'target_players')) {
            Schema::table('player_posts', function (Blueprint $table): void {
                $table->dropColumn('target_players');
            });
        }

        // Keep historical status values intact on rollback. Existing rows may
        // already use expired/left from earlier lifecycle migrations.
    }
};
