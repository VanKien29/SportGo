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
        Schema::create('partner_liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_contract_id')->constrained('partner_contracts')->onDelete('cascade');
            $table->foreignId('termination_request_id')->constrained('partner_termination_requests')->onDelete('cascade');
            $table->string('file_path');
            $table->string('status')->default('completed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_liquidations');
    }
};
