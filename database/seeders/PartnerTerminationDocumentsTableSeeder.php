<?php

namespace Database\Seeders;

use App\Models\GeneratedDocument;
use App\Models\PartnerTerminationDocument;
use App\Models\PartnerTerminationRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerTerminationDocumentsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_termination_documents') || ! Schema::hasTable('generated_documents')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();

        if (! $admin) {
            return;
        }

        $this->seedDocument('TERM-MUTUAL-CG-001', 'mutual_liquidation_minutes', 'DOC-BBTL-CG-001', 'completed', $admin);
        $this->seedDocument('TERM-MUTUAL-CG-001', 'settlement_minutes', 'DOC-BBQT-CG-001', 'completed', $admin);
        $this->seedDocument('TERM-MUTUAL-CG-SETTLE', 'mutual_liquidation_minutes', 'DOC-BBTL-CG-SETTLE', 'pending_signature', $admin);
        $this->seedDocument('TERM-OWNER-CG-001', 'owner_termination_request', 'DOC-DYCCD-CG-001', 'signed', $admin);
        $this->seedDocument('TERM-SPORTGO-CG-001', 'unilateral_notice', 'DOC-CVCD-CG-001', 'signed', $admin);
        $this->seedDocument('TERM-SPORTGO-CG-DONE', 'unilateral_notice', 'DOC-CVCD-CG-DONE', 'completed', $admin);
        $this->seedDocument('TERM-SPORTGO-CG-DONE', 'settlement_minutes', 'DOC-BBQT-CG-DEBT', 'completed', $admin);
    }

    private function seedDocument(string $terminationCode, string $documentType, string $documentCode, string $status, User $admin): void
    {
        $request = PartnerTerminationRequest::query()->where('termination_code', $terminationCode)->first();
        $document = GeneratedDocument::query()->where('document_code', $documentCode)->first();

        if (! $request || ! $document) {
            return;
        }

        PartnerTerminationDocument::query()->updateOrCreate(
            [
                'partner_termination_request_id' => $request->id,
                'document_type' => $documentType,
            ],
            [
                'generated_document_id' => $document->id,
                'media_id' => null,
                'file_path' => $document->final_file_path ?: $document->generated_file_path,
                'status' => $status,
                'generated_by' => $admin->id,
                'generated_at' => $document->generated_at ?: now()->subDays(3),
            ],
        );
    }
}
