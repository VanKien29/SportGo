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
        Schema::create('user_permission_revokes', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('User bị thu hồi quyền, trỏ users.id.');
            $table->unsignedBigInteger('permission_id')->comment('Quyền bị thu hồi, trỏ permissions.id.');
            $table->enum('scope_type', ['system', 'venue'])->default('system')->comment('Phạm vi thu hồi quyền: system hoặc venue.');
            $table->char('scope_id', 36)->default('00000000-0000-0000-0000-000000000000')->comment('ID phạm vi thu hồi. Với system dùng zero UUID; với venue là venue_clusters.id.');
            $table->char('revoked_by', 36)->nullable()->comment('Người thực hiện thu hồi quyền, trỏ users.id.');
            $table->string('reason', 255)->nullable()->comment('Lý do thu hồi quyền để admin xem lại.');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm thu hồi quyền.; VD: 2026-06-15 18:00:00');
            $table->unique(['user_id', 'permission_id', 'scope_type', 'scope_id'], 'user_permission_revokes_scope_unique');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('revoked_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permission_revokes');
    }
};
