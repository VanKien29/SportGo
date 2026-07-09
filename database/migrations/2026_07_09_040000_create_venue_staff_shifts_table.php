<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->char('venue_cluster_id', 36);
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('venue_cluster_id')
                ->references('id')
                ->on('venue_clusters')
                ->onDelete('cascade');

            $table->index('venue_cluster_id', 'venue_staff_shifts_venue_cluster_id_index');
            $table->index('is_active', 'venue_staff_shifts_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_staff_shifts');
    }
};
