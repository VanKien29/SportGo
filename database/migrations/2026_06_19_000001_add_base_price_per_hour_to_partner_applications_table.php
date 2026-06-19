<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_applications')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_applications', 'base_price_per_hour')) {
                $table->unsignedInteger('base_price_per_hour')
                    ->default(0)
                    ->after('court_count_total')
                    ->comment('Gia co ban/gio cua cum san khi dang ky doi tac.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_applications') || ! Schema::hasColumn('partner_applications', 'base_price_per_hour')) {
            return;
        }

        Schema::table('partner_applications', function (Blueprint $table): void {
            $table->dropColumn('base_price_per_hour');
        });
    }
};
