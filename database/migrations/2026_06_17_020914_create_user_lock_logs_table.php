<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lock_logs', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('User bị khóa/mở khóa');
            $table->enum('action', ['locked', 'unlocked'])->comment('Hành động khóa hoặc mở khóa');
            $table->text('reason')->nullable()->comment('Lý do khóa/mở khóa');
            $table->char('locked_by', 36)->nullable()->comment('Admin thực hiện, NULL nếu tự động');
            $table->boolean('auto_triggered')->default(false)->comment('Khóa tự động hay thủ công');
            $table->timestamp('lock_until')->nullable()->comment('Thời điểm hết khóa, NULL = vĩnh viễn');
            $table->json('policy_snapshot')->nullable()->comment('Snapshot policy tại thời điểm khóa tự động');
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lock_logs');
    }
};
