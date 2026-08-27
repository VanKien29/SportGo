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
            $table->unsignedSmallInteger('lock_lead_minutes')->default(30)->after('needed_players')
                ->comment('Số phút trước giờ bắt đầu để khóa nhận yêu cầu tham gia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_posts', function (Blueprint $table) {
            $table->dropColumn('lock_lead_minutes');
        });
    }
};
