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
        Schema::create('court_type_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('player_count')->default(2);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->char('requested_by', 36);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('court_types')->onDelete('set null');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_type_requests');
    }
};
