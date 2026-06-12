<?php

namespace Database\Seeders;

use App\Models\GeneratedDocument;
use App\Models\GeneratedDocumentSignature;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class GeneratedDocumentSignaturesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('generated_document_signatures') || ! Schema::hasTable('generated_documents')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();

        if (! $owner || ! $admin) {
            return;
        }

        $this->signed('DOC-HD-SG-CG-001', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Hộ kinh doanh SportGo Cầu Giấy', now()->subDays(9));
        $this->signed('DOC-HD-SG-CG-001', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subDays(8));

        $this->pending('DOC-HD-SG-DD-001', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Hộ kinh doanh SportGo Đống Đa');

        $this->signed('DOC-HD-SG-BD-001', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Hộ kinh doanh SportGo Ba Đình', now()->subDays(2));
        $this->pending('DOC-HD-SG-BD-001', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo');

        $this->signed('DOC-HD-SG-CG-OLD', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Hộ kinh doanh SportGo Cầu Giấy', now()->subMonths(8)->addDay());
        $this->signed('DOC-HD-SG-CG-OLD', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subMonths(8)->addDays(2));

        $this->signed('DOC-DYCCD-CG-001', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Đối tác chủ sân', now()->subDays(11));

        $this->signed('DOC-BBTL-CG-001', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Đối tác chủ sân', now()->subDays(5));
        $this->signed('DOC-BBTL-CG-001', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subDays(4));
        $this->pending('DOC-BBTL-CG-SETTLE', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Đối tác chủ sân');
        $this->pending('DOC-BBTL-CG-SETTLE', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo');

        $this->signed('DOC-CVCD-CG-001', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subDays(4));
        $this->signed('DOC-CVCD-CG-DONE', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subDays(34));

        $this->signed('DOC-BBQT-CG-001', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Đối tác chủ sân', now()->subDays(2));
        $this->signed('DOC-BBQT-CG-001', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subDays(2));
        $this->signed('DOC-BBQT-CG-DEBT', 'owner', $owner, $owner->full_name, 'Chủ sân', 'Đối tác chủ sân', now()->subDays(7));
        $this->signed('DOC-BBQT-CG-DEBT', 'sportgo', $admin, 'SportGo Admin', 'Đại diện SportGo', 'SportGo', now()->subDays(7));
    }

    private function signed(
        string $documentCode,
        string $signerSide,
        User $signer,
        string $fullName,
        string $title,
        string $organization,
        mixed $signedAt
    ): void {
        $this->seedSignature($documentCode, $signerSide, $signer, $fullName, $title, $organization, 'signed', $signedAt);
    }

    private function pending(
        string $documentCode,
        string $signerSide,
        User $signer,
        string $fullName,
        string $title,
        string $organization
    ): void {
        $this->seedSignature($documentCode, $signerSide, $signer, $fullName, $title, $organization, 'pending', null);
    }

    private function seedSignature(
        string $documentCode,
        string $signerSide,
        User $signer,
        string $fullName,
        string $title,
        string $organization,
        string $status,
        mixed $signedAt
    ): void {
        $document = GeneratedDocument::query()->where('document_code', $documentCode)->first();

        if (! $document) {
            return;
        }

        GeneratedDocumentSignature::query()->updateOrCreate(
            [
                'generated_document_id' => $document->id,
                'signer_side' => $signerSide,
            ],
            [
                'signer_user_id' => $signer->id,
                'signer_full_name' => $fullName,
                'signer_title' => $title,
                'signer_organization' => $organization,
                'signature_method' => 'typed_confirm',
                'signature_media_id' => null,
                'signed_at' => $signedAt,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SportGo Seeder',
                'status' => $status,
                'reject_reason' => null,
            ],
        );
    }
}
