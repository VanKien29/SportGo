<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_packages', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('name', 100)->comment('Ten goi thanh vien he thong.');
            $table->enum('type', ['free', 'saving', 'pro'])->default('free')->comment('Loai goi.');
            $table->decimal('monthly_price', 12, 2)->nullable()->comment('Gia theo thang, null neu admin chua cau hinh.');
            $table->decimal('quarterly_price', 12, 2)->nullable()->comment('Gia 3 thang.');
            $table->decimal('yearly_price', 12, 2)->nullable()->comment('Gia 12 thang.');
            $table->unsignedTinyInteger('voucher_count_per_month')->default(0)->comment('So voucher VIP phat moi thang.');
            $table->decimal('voucher_discount_percent', 5, 2)->default(0)->comment('Phan tram giam cua voucher VIP.');
            $table->decimal('voucher_min_order_amount', 12, 2)->default(0)->comment('Gia tri don toi thieu de dung voucher VIP.');
            $table->decimal('voucher_max_discount_amount', 12, 2)->nullable()->comment('Tran giam gia toi da moi voucher VIP.');
            $table->decimal('cashback_percent', 5, 2)->default(0)->comment('Phan tram cashback vao vi sau booking.');
            $table->integer('match_post_limit_per_month')->default(5)->comment('So bai tuyen giao luu moi thang, -1 la khong gioi han.');
            $table->boolean('priority_complaint')->default(false)->comment('Uu tien khieu nai.');
            $table->string('badge_name', 100)->nullable()->comment('Ten huy hieu hien thi tren profile.');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0)->comment('Thu tu hien thi.');
            $table->timestamps();

            $table->index(['type', 'is_active'], 'membership_packages_type_active_index');
            $table->index('sort_order', 'membership_packages_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_packages');
    }
};
