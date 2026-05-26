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
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('User nhận role, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001');
            $table->unsignedBigInteger('role_id')->comment('Role được gán, trỏ roles.id.; VD: venue_owner');
            $table->string('scope_type')->comment('Phạm vi role: system là toàn hệ thống, venue là trong một cụm sân. Giá trị enum: system=hệ thống; venue=theo cụm sân.; VD: booking_reminder');
            $table->char('scope_id', 36)->unique()->comment('ID phạm vi. Với system dùng zero UUID để unique ổn định; với venue là venue_clusters.id.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('granted_by', 36)->nullable()->comment('Admin/chủ sân đã gán role, trỏ users.id.; VD: 10000000-0000-0000-0000-000000000001');
            $table->timestamp('created_at')->nullable()->comment('Thời điểm gán role.; VD: 2026-06-15 18:00:00');
            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'user_roles_scope_unique');
            $table->foreign('granted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
