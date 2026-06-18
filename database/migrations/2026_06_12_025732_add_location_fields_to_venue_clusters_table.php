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
        Schema::table('venue_clusters', function (Blueprint $table) {
            $table->string('province', 255)->nullable()->after('phone_contact')->comment('Tỉnh/Thành phố');
            $table->string('ward', 255)->nullable()->after('province')->comment('Xã/Phường');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venue_clusters', function (Blueprint $table) {
            $table->dropColumn(['province', 'ward']);
        });
    }
};
