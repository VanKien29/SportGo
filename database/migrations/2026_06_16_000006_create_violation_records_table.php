<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('violation_records')) {
            return;
        }

        Schema::create('violation_records', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->string('target_type', 50);
            $table->char('target_id', 36);
            $table->unsignedTinyInteger('violation_count')->default(1);
            $table->timestamp('last_violation_at')->nullable();
            $table->string('last_action_type', 50)->nullable();
            $table->timestamp('last_action_expires_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id'], 'violation_records_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_records');
    }
};
