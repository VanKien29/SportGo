<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('owner_withdrawal_requests')) {
            return;
        }

        Schema::table('owner_withdrawal_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('owner_withdrawal_requests', 'source')) {
                $table->enum('source', ['manual', 'partner_termination_settlement'])->nullable()->after('request_code');
            }
            if (! Schema::hasColumn('owner_withdrawal_requests', 'partner_settlement_id')) {
                $table->char('partner_settlement_id', 36)->nullable()->after('source');
            }
            if (! Schema::hasColumn('owner_withdrawal_requests', 'partner_termination_request_id')) {
                $table->unsignedBigInteger('partner_termination_request_id')->nullable()->after('partner_settlement_id');
            }
            if (! Schema::hasColumn('owner_withdrawal_requests', 'auto_created')) {
                $table->boolean('auto_created')->default(false)->after('partner_termination_request_id');
            }
        });

        try {
            DB::statement('ALTER TABLE `owner_withdrawal_requests` ADD INDEX `owner_withdrawal_requests_source_index` (`source`)');
        } catch (\Exception $e) {
            // Ignore if index already exists
        }

        $indexes = [
            'owner_withdrawal_requests_settlement_index' => 'partner_settlement_id',
            'owner_withdrawal_requests_termination_index' => 'partner_termination_request_id',
        ];
        
        foreach ($indexes as $indexName => $column) {
            try {
                DB::statement("ALTER TABLE `owner_withdrawal_requests` ADD INDEX `$indexName` (`$column`)");
            } catch (\Exception $e) {}
        }
        
        try {
            Schema::table('owner_withdrawal_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('owner_withdrawal_requests', 'partner_settlement_id')) {
                    $table->foreign('partner_settlement_id', 'owner_withdrawal_requests_settlement_foreign')
                        ->references('id')->on('partner_settlements')->onDelete('restrict');
                }
                if (Schema::hasColumn('owner_withdrawal_requests', 'partner_termination_request_id')) {
                    $table->foreign('partner_termination_request_id', 'owner_withdrawal_requests_termination_foreign')
                        ->references('id')->on('partner_termination_requests')->onDelete('restrict');
                }
            });
        } catch (\Exception $e) {}
    }

    public function down(): void
    {
        if (! Schema::hasTable('owner_withdrawal_requests')) {
            return;
        }

        Schema::table('owner_withdrawal_requests', function (Blueprint $table): void {
            foreach ([
                'owner_withdrawal_requests_settlement_foreign',
                'owner_withdrawal_requests_termination_foreign',
            ] as $foreign) {
                try {
                    $table->dropForeign($foreign);
                } catch (Throwable) {
                    // Foreign key may not exist on partially migrated databases.
                }
            }

            foreach ([
                'owner_withdrawal_requests_source_index',
                'owner_withdrawal_requests_settlement_index',
                'owner_withdrawal_requests_termination_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                    // Index may not exist on partially migrated databases.
                }
            }
        });

        Schema::table('owner_withdrawal_requests', function (Blueprint $table): void {
            foreach (['auto_created', 'partner_termination_request_id', 'partner_settlement_id', 'source'] as $column) {
                if (Schema::hasColumn('owner_withdrawal_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
