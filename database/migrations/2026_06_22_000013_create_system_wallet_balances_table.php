<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_wallet_balances', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->char('system_bank_account_id', 36)->unique()->comment('Tai khoan ngan hang he thong.');
            $table->decimal('current_balance', 18, 2)->default(0)->comment('So du hien tai doc tu tai khoan that.');
            $table->timestamp('last_synced_at')->nullable()->comment('Thoi diem dong bo so du gan nhat.');
            $table->decimal('alert_threshold', 18, 2)->nullable()->comment('Nguong canh bao so du thap.');
            $table->boolean('is_alert_enabled')->default(true)->comment('Bat tat canh bao.');
            $table->timestamp('last_alerted_at')->nullable()->comment('Thoi diem gui canh bao gan nhat.');
            $table->timestamps();

            $table->foreign('system_bank_account_id')->references('id')->on('system_bank_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_wallet_balances');
    }
};
