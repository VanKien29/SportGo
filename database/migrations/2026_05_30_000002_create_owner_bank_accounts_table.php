<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('owner_bank_accounts')) {
            return;
        }

        Schema::create('owner_bank_accounts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('owner_id', 36)->comment('User chủ sân sở hữu tài khoản nhận tiền.');
            $table->char('partner_application_id', 36)->nullable()->comment('Hồ sơ đăng ký chủ sân đã cung cấp tài khoản này.');
            $table->string('bank_name', 100)->comment('Tên ngân hàng.');
            $table->string('bank_code', 50)->comment('Mã ngân hàng dùng cho đối soát/chuyển khoản.');
            $table->string('account_number', 50)->comment('Số tài khoản nhận tiền.');
            $table->string('account_holder_name', 150)->comment('Tên chủ tài khoản.');
            $table->string('branch_name', 150)->nullable()->comment('Chi nhánh ngân hàng nếu có.');
            $table->enum('status', ['pending', 'active', 'rejected', 'inactive'])->default('pending')->comment('Trạng thái xác minh tài khoản.');
            $table->boolean('is_default')->default(false)->comment('Tài khoản nhận tiền mặc định của owner.');
            $table->char('verified_by', 36)->nullable()->comment('Admin xác minh tài khoản.');
            $table->timestamp('verified_at')->nullable()->comment('Thời điểm xác minh.');
            $table->text('rejected_reason')->nullable()->comment('Lý do từ chối tài khoản nhận tiền.');
            $table->timestamps();

            $table->unique(['owner_id', 'bank_code', 'account_number'], 'owner_bank_accounts_owner_bank_unique');
            $table->index(['owner_id', 'status'], 'owner_bank_accounts_owner_status_index');
            $table->index(['status', 'is_default'], 'owner_bank_accounts_status_default_index');
            $table->index('partner_application_id', 'owner_bank_accounts_partner_application_index');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('partner_application_id')->references('id')->on('partner_applications')->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_bank_accounts');
    }
};
