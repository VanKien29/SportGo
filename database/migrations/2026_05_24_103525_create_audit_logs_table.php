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
            $table->char('actor_id', 36)->nullable()->comment('User thực hiện hành động nhạy cảm; nullable nếu do hệ thống tự động.');
            $table->string('action', 100)->comment('Mã hành động như user.locked, venue.locked, report.resolved.');
            $table->string('entity_type', 100)->comment('Loại đối tượng bị tác động; logical reference.');
            $table->string('entity_id', 100)->comment('ID đối tượng bị tác động; logical reference.');
            $table->json('old_values')->nullable()->comment('JSON dữ liệu trước khi thay đổi.');
            $table->json('new_values')->nullable()->comment('JSON dữ liệu sau khi thay đổi.');
            $table->string('context', 50)->nullable()->comment('Ngữ cảnh thao tác như admin, moderation, payment.');
            $table->string('ip_address', 45)->nullable()->comment('IP của người thực hiện nếu có.');
            $table->string('user_agent', 500)->nullable()->comment('User agent/thiết bị của người thực hiện nếu có.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm ghi audit log.');
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_type_entity_id_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('context', 'audit_logs_context_index');
            $table->index('created_at', 'audit_logs_created_at_index');
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
