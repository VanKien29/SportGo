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
        Schema::table('venue_posts', function (Blueprint $table) {
            $table->string('short_description', 500)->nullable(false)->after('content');
            $table->string('meta_title', 255)->nullable()->after('short_description');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venue_posts', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'meta_title', 'meta_description']);
        });
    }
};
