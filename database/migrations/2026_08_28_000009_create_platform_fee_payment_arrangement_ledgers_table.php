<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fee_payment_arrangement_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('arrangement_id');
            $table->unsignedBigInteger('ledger_id');
            $table->date('original_due_date')->nullable();
            $table->timestamps();

            $table->unique(['arrangement_id', 'ledger_id'], 'pf_arrangement_ledgers_unique');
            $table->index('ledger_id', 'pf_arrangement_ledgers_ledger_index');
            $table->foreign('arrangement_id')->references('id')->on('platform_fee_payment_arrangements')->cascadeOnDelete();
            $table->foreign('ledger_id')->references('id')->on('venue_platform_fee_ledgers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fee_payment_arrangement_ledgers');
    }
};
