<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_staff_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->char('venue_cluster_id', 36);
            $table->char('user_id', 36);
            $table->unsignedBigInteger('venue_staff_shift_id')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['scheduled', 'checked_in', 'checked_out', 'absent', 'cancelled'])->default('scheduled');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('venue_cluster_id')
                ->references('id')
                ->on('venue_clusters')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('venue_staff_shift_id')
                ->references('id')
                ->on('venue_staff_shifts')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('venue_cluster_id', 'venue_staff_shift_schedules_venue_cluster_id_index');
            $table->index('user_id', 'venue_staff_shift_schedules_user_id_index');
            $table->index('date', 'venue_staff_shift_schedules_date_index');
            $table->index('status', 'venue_staff_shift_schedules_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_staff_shift_schedules');
    }
};
