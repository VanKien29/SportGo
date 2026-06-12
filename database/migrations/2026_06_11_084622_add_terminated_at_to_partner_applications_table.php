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
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->timestamp('terminated_at')->nullable()->after('reviewed_at')->comment('Thời điểm chấm dứt hợp đồng hợp tác');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_applications', function (Blueprint $table) {
            $table->dropColumn('terminated_at');
        });
    }
};
