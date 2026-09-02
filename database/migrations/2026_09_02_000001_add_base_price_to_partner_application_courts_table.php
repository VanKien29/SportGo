<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_application_courts')) {
            return;
        }

        Schema::table('partner_application_courts', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_application_courts', 'base_price_per_hour')) {
                $table->unsignedBigInteger('base_price_per_hour')->nullable()->after('note')
                    ->comment('Giá thuê cơ bản mỗi giờ riêng cho sân con này (VNĐ), nullable nghĩa là dùng giá chung của cụm sân');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_application_courts')) {
            return;
        }

        Schema::table('partner_application_courts', function (Blueprint $table): void {
            if (Schema::hasColumn('partner_application_courts', 'base_price_per_hour')) {
                $table->dropColumn('base_price_per_hour');
            }
        });
    }
};
