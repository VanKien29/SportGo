<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('generated_documents')) {
            return;
        }

        Schema::table('generated_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('generated_documents', 'partner_contract_id') && Schema::hasTable('partner_contracts')) {
                $table->unsignedBigInteger('partner_contract_id')->nullable()->change();
                $table->foreign('partner_contract_id', 'generated_documents_contract_foreign')
                    ->references('id')->on('partner_contracts')->onDelete('set null');
            }

            if (Schema::hasColumn('generated_documents', 'partner_termination_request_id') && Schema::hasTable('partner_termination_requests')) {
                $table->unsignedBigInteger('partner_termination_request_id')->nullable()->change();
                $table->foreign('partner_termination_request_id', 'generated_documents_termination_foreign')
                    ->references('id')->on('partner_termination_requests')->onDelete('set null');
            }

            if (Schema::hasColumn('generated_documents', 'partner_settlement_id') && Schema::hasTable('partner_settlements')) {
                $table->foreign('partner_settlement_id', 'generated_documents_settlement_foreign')
                    ->references('id')->on('partner_settlements')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('generated_documents')) {
            return;
        }

        Schema::table('generated_documents', function (Blueprint $table): void {
            foreach ([
                'generated_documents_contract_foreign',
                'generated_documents_termination_foreign',
                'generated_documents_settlement_foreign',
            ] as $foreign) {
                try {
                    $table->dropForeign($foreign);
                } catch (Throwable) {
                    // Foreign key may not exist on partially migrated databases.
                }
            }
        });
    }
};
