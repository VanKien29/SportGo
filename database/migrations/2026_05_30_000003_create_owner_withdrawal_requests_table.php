<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('owner_withdrawal_requests')) {
            return;
        }

        Schema::create('owner_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 30)->unique()->comment('Mã yêu cầu rút tiền để admin/owner tra cứu.');
            $table->unsignedBigInteger('owner_id')->comment('Chủ sân yêu cầu rút tiền.');
            $table->unsignedBigInteger('owner_wallet_id')->comment('Ví owner bị trừ/giữ tiền.');
            $table->unsignedBigInteger('owner_bank_account_id')->comment('Tài khoản nhận tiền owner chọn.');
            $table->decimal('amount', 14, 2)->comment('Số tiền owner yêu cầu rút.');
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending')->comment('Trạng thái xử lý rút tiền.');
            $table->text('owner_note')->nullable()->comment('Ghi chú của owner khi gửi yêu cầu.');
            $table->unsignedBigInteger('reviewed_by')->nullable()->comment('Admin duyệt/từ chối yêu cầu.');
            $table->timestamp('reviewed_at')->nullable()->comment('Thời điểm duyệt/từ chối.');
            $table->text('review_note')->nullable()->comment('Ghi chú nội bộ khi duyệt.');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/hủy/thất bại.');
            $table->unsignedBigInteger('completed_by')->nullable()->comment('Admin xác nhận đã chuyển tiền.');
            $table->timestamp('completed_at')->nullable()->comment('Thời điểm hoàn tất chuyển tiền.');
            $table->string('transfer_reference', 100)->nullable()->comment('Mã giao dịch chuyển khoản thực tế.');
            $table->json('metadata')->nullable()->comment('Dữ liệu phụ cho đối soát.');
            $table->timestamp('requested_at')->useCurrent()->comment('Thời điểm owner gửi yêu cầu.');
            $table->timestamps();

            $table->index(['owner_id', 'status'], 'owner_withdrawal_requests_owner_status_index');
            $table->index(['status', 'requested_at'], 'owner_withdrawal_requests_status_requested_index');
            $table->index('owner_wallet_id', 'owner_withdrawal_requests_wallet_index');
            $table->index('owner_bank_account_id', 'owner_withdrawal_requests_bank_index');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('owner_wallet_id')->references('id')->on('owner_wallets')->onDelete('restrict');
            $table->foreign('owner_bank_account_id')->references('id')->on('owner_bank_accounts')->onDelete('restrict');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_withdrawal_requests');
    }
};
