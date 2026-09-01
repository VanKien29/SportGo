<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_promotions', function (Blueprint $table): void {
            $table->boolean('applies_to_deferred')->default(false)->after('stackable_with_prepay');
            $table->boolean('applies_to_bridge')->default(false)->after('applies_to_deferred');
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_promotions', function (Blueprint $table): void {
            $table->dropColumn(['applies_to_deferred', 'applies_to_bridge']);
        });
    }
};
