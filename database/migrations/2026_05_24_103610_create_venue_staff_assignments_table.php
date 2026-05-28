<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_staff_assignments', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36);
            $table->char('venue_cluster_id', 36);
            $table->enum('scope_type', ['all_cluster', 'court_type'])->default('all_cluster');
            $table->unsignedBigInteger('court_type_id')->nullable();
            $table->string('scope_key', 50)->default('all');
            $table->char('assigned_by', 36)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'venue_cluster_id', 'scope_key'], 'venue_staff_assignments_unique');
            $table->index('venue_cluster_id', 'venue_staff_assignments_venue_cluster_id_foreign');
            $table->index('court_type_id', 'venue_staff_assignments_court_type_id_foreign');
            $table->index('scope_type', 'venue_staff_assignments_scope_type_index');
            $table->index('scope_key', 'venue_staff_assignments_scope_key_index');
            $table->index('status', 'venue_staff_assignments_status_index');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('court_type_id')->references('id')->on('court_types')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('venue_cluster_id')->references('id')->on('venue_clusters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_staff_assignments');
    }
};
