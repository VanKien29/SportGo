<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PartnerDocumentLinksSeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! Schema::hasTable('partner_applications')
            || ! Schema::hasTable('partner_contracts')
            || ! Schema::hasTable('generated_documents')
        ) {
            return;
        }

        $contractDocuments = [
            'CONTRACT_GREEN_0001' => 'DOC_CONTRACT_0001',
        ];

        foreach ($contractDocuments as $contractCode => $documentCode) {
            $contract = DB::table('partner_contracts')->where('contract_code', $contractCode)->first();
            $documentId = DB::table('generated_documents')->where('document_code', $documentCode)->value('id');

            if (! $contract) {
                continue;
            }

            DB::table('partner_contracts')
                ->where('id', $contract->id)
                ->update([
                    'generated_document_id' => $documentId,
                    'updated_at' => now(),
                ]);

            DB::table('partner_applications')
                ->where('id', $contract->partner_application_id)
                ->update([
                    'current_contract_id' => $contract->id,
                    'updated_at' => now(),
                ]);

            if ($documentId) {
                DB::table('generated_documents')
                    ->where('id', $documentId)
                    ->update([
                        'entity_type' => 'App\\Models\\PartnerApplication',
                        'entity_id' => $contract->partner_application_id,
                        'partner_contract_id' => $contract->id,
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
