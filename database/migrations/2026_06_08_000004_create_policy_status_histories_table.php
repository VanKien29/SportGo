<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('policy_status_histories')) {
            return;
        }

        Schema::create('policy_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->char('system_policy_id', 36);
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->char('changed_by', 36)->nullable();
            $table->string('actor_type', 50)->default('admin');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['system_policy_id', 'created_at'], 'policy_status_histories_policy_created_index');
            $table->foreign('system_policy_id', 'policy_status_histories_policy_foreign')
                ->references('id')->on('system_policies')->onDelete('restrict');
            $table->foreign('changed_by', 'policy_status_histories_changed_by_foreign')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_status_histories');
    }
};
