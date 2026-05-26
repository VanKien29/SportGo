<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->char('system_policy_id', 36);
            $table->string('policy_version', 50);
            $table->timestamp('accepted_at')->useCurrent();

            $table->unique(['user_id', 'system_policy_id', 'policy_version'], 'user_policy_acceptances_unique');
            $table->foreign('system_policy_id')->references('id')->on('system_policies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_policy_acceptances');
    }
};
