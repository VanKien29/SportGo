<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_posts', function (Blueprint $table) {
            $table->unique('booking_id', 'player_posts_booking_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('player_posts', function (Blueprint $table) {
            $table->dropUnique('player_posts_booking_id_unique');
        });
    }
};
