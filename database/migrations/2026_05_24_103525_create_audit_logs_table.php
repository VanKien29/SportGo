<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('actor_id', 36)->nullable()->comment('User thực hiện hành động nhạy cảm; nullable nếu do hệ thống tự động.; VD: 10000000-0000-0000-0000-000000000001');
            $table->string('action', 100)->comment('Mã hành động như user.locked, venue.locked, report.resolved.; VD: giá trị mẫu');
            $table->string('entity_type', 100)->comment('Loại đối tượng bị tác động; logical reference.; VD: booking_reminder');
            $table->string('entity_id', 100)->comment('ID đối tượng bị tác động; logical reference.; VD: 10000000-0000-0000-0000-000000000001');
            $table->json('old_values')->nullable()->comment('JSON dữ liệu trước khi thay đổi.; VD: {"key":"value"}');
            $table->json('new_values')->nullable()->comment('JSON dữ liệu sau khi thay đổi.; VD: {"key":"value"}');
            $table->string('context', 50)->nullable()->comment('Ngữ cảnh thao tác như admin, moderation, payment.; VD: giá trị mẫu');
            $table->string('ip_address', 45)->nullable()->comment('IP của người thực hiện nếu có.; VD: 123 Nguyễn Trãi, Hà Nội');
            $table->string('user_agent', 500)->nullable()->comment('User agent/thiết bị của người thực hiện nếu có.; VD: Mozilla/5.0');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm ghi audit log.; VD: 2026-06-15 18:00:00');
            $table->timestamp('created_at')->nullable();
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_type_entity_id_index');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
