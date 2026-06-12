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
        Schema::table('court_types', function (Blueprint $table) {
            $table->double('default_layout_w')->nullable()->after('is_active')->comment('Default width of court on layout canvas (px/decimeters)');
            $table->double('default_layout_h')->nullable()->after('default_layout_w')->comment('Default height of court on layout canvas (px/decimeters)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('court_types', function (Blueprint $table) {  
            $table->dropColumn(['default_layout_w', 'default_layout_h']);
        });
    }
};
