<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_settlement_items')) {
            return;
        }

        Schema::create('partner_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->char('partner_settlement_id', 36);
            $table->enum('item_type', ['owner_wallet_balance', 'pending_withdrawal', 'platform_fee_remaining_refund', 'unpaid_platform_fee', 'penalty', 'adjustment']);
            $table->text('description');
            $table->decimal('amount', 14, 2);
            $table->enum('direction', ['payable_to_owner', 'receivable_from_owner']);
            $table->string('reference_type', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['partner_settlement_id', 'item_type'], 'partner_settlement_items_settlement_type_index');
            $table->foreign('partner_settlement_id', 'partner_settlement_items_settlement_foreign')
                ->references('id')->on('partner_settlements')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_settlement_items');
    }
};
