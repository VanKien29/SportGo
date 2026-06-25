<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->comment('User mua goi VIP.');
            $table->char('package_id', 36)->comment('Goi thanh vien da mua.');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly')
                ->comment('Chu ky da mua.');
            $table->timestamp('started_at')->comment('Thoi diem kich hoat.');
            $table->timestamp('expires_at')->comment('Thoi diem het han.');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->decimal('paid_amount', 12, 2)->default(0)->comment('Tien thuc te da tra.');
            $table->string('payment_ref', 100)->nullable()->comment('Ma thanh toan doi soat.');
            $table->unsignedInteger('month_post_count')->default(0)->comment('So bai tuyen giao luu da dang trong thang.');
            $table->timestamp('month_post_reset_at')->nullable()->comment('Thoi diem reset count thang moi.');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'user_subscriptions_user_status_index');
            $table->index(['user_id', 'expires_at'], 'user_subscriptions_user_expires_index');
            $table->index(['status', 'expires_at'], 'user_subscriptions_status_expires_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('membership_packages')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
