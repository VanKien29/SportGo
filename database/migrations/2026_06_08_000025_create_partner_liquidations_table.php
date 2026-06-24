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
        if (Schema::hasTable('partner_liquidations')) {
            return;
        }

        Schema::create('partner_liquidations', function (Blueprint $table) {
            $table->id();
            $table->char('partner_contract_id', 36);
            $table->char('termination_request_id', 36);
            $table->string('file_path');
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->foreign('partner_contract_id')
                ->references('id')->on('partner_contracts')->onDelete('cascade');
            $table->foreign('termination_request_id')
                ->references('id')->on('partner_termination_requests')->onDelete('cascade');
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
