<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->comment('Payment gốc cần hoàn tiền.');
            $table->unsignedBigInteger('booking_id')->comment('Booking liên quan, denormalized.');
            $table->decimal('amount', 12, 2)->comment('Số tiền yêu cầu hoàn.');
            $table->text('reason')->nullable()->comment('Lý do yêu cầu hoàn.');
            $table->enum('status', ['pending_confirmation', 'processing', 'completed', 'failed', 'rejected'])->default('pending_confirmation')->comment('Trạng thái refund.');
            $table->text('status_reason')->nullable()->comment('Lý do từ chối/thất bại.');
            $table->unsignedBigInteger('processed_by')->nullable()->comment('Admin xử lý hoàn tiền.');
            $table->timestamp('processed_at')->nullable()->comment('Thời điểm xử lý.');
            $table->timestamps();
            $table->index(['booking_id', 'status'], 'refunds_booking_id_status_index');
            $table->index(['status', 'created_at'], 'refunds_status_created_at_index');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('restrict');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
        });
    }
    public function down(): void { Schema::dropIfExists('refunds'); }
};
