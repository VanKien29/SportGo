<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_bank_accounts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 100)->default('Tài khoản nhận tiền hệ thống')->comment('Tên gợi nhớ nội bộ.');
            $table->string('bank_name', 100)->nullable()->comment('Tên ngân hàng hiển thị cho người dùng.');
            $table->string('bank_code', 50)->comment('Mã ngân hàng dùng để tạo QR SePay.');
            $table->string('account_number', 50)->comment('Số tài khoản hệ thống nhận tiền.');
            $table->string('account_holder_name', 150)->comment('Tên chủ tài khoản hệ thống.');
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('Trạng thái sử dụng.');
            $table->boolean('is_default')->default(false)->comment('Tài khoản nhận tiền mặc định.');
            $table->timestamps();

            $table->unique(['bank_code', 'account_number'], 'system_bank_accounts_bank_account_unique');
            $table->index(['status', 'is_default'], 'system_bank_accounts_status_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_bank_accounts');
    }
};
