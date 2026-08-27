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
            $table->string('skill_level', 50)->default('all')->after('needed_players')->comment('Trình độ: all, beginner, intermediate, advanced');
            $table->string('cost_type', 30)->default('free')->after('cost_per_player')->comment('Hình thức chi phí: free, split, custom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_posts', function (Blueprint $table) {
            $table->dropColumn(['skill_level', 'cost_type']);
        });
    }
};
