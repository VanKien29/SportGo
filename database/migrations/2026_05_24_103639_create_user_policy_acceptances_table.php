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
        Schema::create('user_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->comment('User đã chấp nhận chính sách.; VD: 10000000-0000-0000-0000-000000000001');
            $table->char('system_policy_id', 36)->comment('Chính sách được user chấp nhận.; VD: 10000000-0000-0000-0000-000000000001');
            $table->integer('policy_version')->comment('Version chính sách user đã chấp nhận.; VD: 1');
            $table->timestamp('accepted_at')->comment('Thời điểm user bấm chấp nhận.; VD: 18:00:00');
            $table->unique(['user_id', 'system_policy_id', 'policy_version'], 'user_policy_acceptances_unique');
            $table->foreign('system_policy_id')->references('id')->on('system_policies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_policy_acceptances');
    }
};
