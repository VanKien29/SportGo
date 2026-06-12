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
        Schema::table('venue_courts', function (Blueprint $table) {
            $table->double('layout_x')->nullable()->after('sort_order')->comment('X position on layout canvas');
            $table->double('layout_y')->nullable()->after('layout_x')->comment('Y position on layout canvas');
            $table->double('layout_w')->nullable()->after('layout_y')->comment('Width of court on layout canvas');
            $table->double('layout_h')->nullable()->after('layout_w')->comment('Height of court on layout canvas');
            $table->integer('layout_rotation')->default(0)->after('layout_h')->comment('Rotation in degrees (0-359)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venue_courts', function (Blueprint $table) {
            $table->dropColumn(['layout_x', 'layout_y', 'layout_w', 'layout_h', 'layout_rotation']);
        });
    }
};
