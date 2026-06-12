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
        Schema::create('partner_termination_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('partner_application_id')->constrained('partner_applications')->onDelete('cascade');
            $table->foreignUuid('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('type'); // mutual, unilateral
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_termination_requests');
    }
};
