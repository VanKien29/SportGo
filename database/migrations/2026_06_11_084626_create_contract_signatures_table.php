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
        if (Schema::hasTable('contract_signatures')) {
            return;
        }

        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->char('partner_contract_id', 36);
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('sign_role'); // owner or admin
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->foreign('partner_contract_id')
                ->references('id')->on('partner_contracts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_signatures');
    }
};
