<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_service_periods', function (Blueprint $table): void {
            $table->date('reference_period_start')->nullable()->after('period_end');
            $table->date('reference_period_end')->nullable()->after('reference_period_start');
            $table->unsignedSmallInteger('service_days')->nullable()->after('reference_period_end');
            $table->unsignedSmallInteger('reference_days')->nullable()->after('service_days');
            $table->string('rounding_rule', 40)->default('half_up_vnd')->after('reference_days');
            $table->unsignedBigInteger('replacement_of_id')->nullable()->after('ledger_id');

            $table->foreign('replacement_of_id')->references('id')->on('platform_fee_service_periods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_service_periods', function (Blueprint $table): void {
            $table->dropForeign(['replacement_of_id']);
            $table->dropColumn([
                'reference_period_start',
                'reference_period_end',
                'service_days',
                'reference_days',
                'rounding_rule',
                'replacement_of_id',
            ]);
        });
    }
};
