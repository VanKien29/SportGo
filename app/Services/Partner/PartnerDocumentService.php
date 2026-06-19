<?php

namespace App\Services\Partner;

use App\Models\GeneratedDocument;
use App\Models\GeneratedDocumentSignature;
use App\Models\Media;
use App\Models\DocumentTemplate;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class PartnerDocumentService
{
    private const TEMPLATE_FALLBACKS = [
        'partner_application_form' => 'Mau_01_Don_de_nghi_dang_ky_doi_tac_chu_san_SportGo.docx',
        'partner_contract' => 'Mau_02_Hop_dong_hop_tac_doi_tac_SportGo.docx',
        'termination_request' => 'Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo.docx',
        'mutual_liquidation_minutes' => 'Mau_04_Bien_ban_thanh_ly_hop_dong_hai_ben_SportGo.docx',
        'unilateral_termination_notice' => 'Mau_05_Cong_van_cham_dut_hop_dong_don_phuong_SportGo.docx',
        'settlement_minutes' => 'Mau_06_Bien_ban_quyet_toan_cham_dut_hop_tac_SportGo.docx',
    ];

    private const DOCUMENT_PREFIXES = [
        'partner_application_form' => 'DKDT',
        'partner_contract' => 'HDHT',
        'termination_request' => 'DYCCD',
        'mutual_liquidation_minutes' => 'BBTL',
        'unilateral_termination_notice' => 'CVCD',
        'settlement_minutes' => 'BBQT',
    ];

    public function generateDocument(
        string $documentType,
        Model $reference,
        array $renderData,
        ?User $actor = null,
        array $context = []
    ): GeneratedDocument {
        $template = $this->activeTemplate($documentType);
        $documentCode = $this->uniqueDocumentCode($documentType);
        $filePath = 'generated-documents/' . now()->format('Y/m') . '/' . $documentCode . '.docx';
        $title = $context['title'] ?? $this->defaultTitle($documentType, $renderData);

        $sourcePath = $template ? Storage::disk($template->storage_disk)->path($template->file_path) : null;
        if (! $sourcePath || ! is_file($sourcePath)) {
            $sourcePath = $this->fallbackTemplatePath($documentType);
        }

        if (! $sourcePath || ! is_file($sourcePath)) {
            throw new RuntimeException("Không tìm thấy template DOCX cho {$documentType}.");
        }

        Storage::disk('local')->put($filePath, file_get_contents($sourcePath));
        $this->replaceDocxPlaceholders(Storage::disk('local')->path($filePath), $renderData);

        $document = GeneratedDocument::create([
            'document_code' => $documentCode,
            'document_type' => $documentType,
            'template_id' => $template?->id,
            'template_version' => $template?->version ?? 1,
            'reference_type' => $context['reference_type'] ?? $reference::class,
            'reference_id' => $context['reference_id'] ?? (string) $reference->getKey(),
            'entity_type' => $context['entity_type'] ?? $reference::class,
            'entity_id' => $context['entity_id'] ?? (string) $reference->getKey(),
            'partner_application_id' => $context['partner_application_id'] ?? null,
            'partner_contract_id' => $context['partner_contract_id'] ?? null,
            'partner_termination_request_id' => $context['partner_termination_request_id'] ?? null,
            'partner_settlement_id' => $context['partner_settlement_id'] ?? null,
            'owner_id' => $context['owner_id'] ?? null,
            'venue_cluster_id' => $context['venue_cluster_id'] ?? null,
            'title' => $title,
            'status' => $context['status'] ?? 'generated',
            'render_data' => $renderData,
            'generated_file_path' => $filePath,
            'final_file_path' => $context['final_file_path'] ?? null,
            'file_hash' => hash_file('sha256', Storage::disk('local')->path($filePath)),
            'generated_by' => $actor?->id,
            'generated_at' => now(),
        ]);

        return $document;
    }

    public function signDocument(
        GeneratedDocument $document,
        User $signer,
        string $signerSide,
        ?string $signatureImage,
        Request $request,
        array $context = []
    ): GeneratedDocumentSignature {
        $signature = GeneratedDocumentSignature::updateOrCreate(
            [
                'generated_document_id' => $document->id,
                'signer_side' => $signerSide,
            ],
            [
                'signer_user_id' => $signer->id,
                'signer_full_name' => $context['signer_full_name'] ?? $signer->full_name ?? $signer->username ?? $signer->email,
                'signer_title' => $context['signer_title'] ?? ($signerSide === 'owner' ? 'Chủ sân' : 'Đại diện SportGo'),
                'signer_organization' => $context['signer_organization'] ?? ($signerSide === 'owner' ? null : 'SportGo'),
                'signature_method' => $signatureImage ? 'drawn' : 'typed_confirm',
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'status' => 'signed',
                'reject_reason' => null,
            ]
        );

        if ($signatureImage) {
            $media = $this->storeSignatureImage($signature, $signatureImage);
            $signature->forceFill(['signature_media_id' => $media->id])->save();
        }

        $document->forceFill([
            'status' => $this->checkAllSigned($document) ? 'completed' : ($signerSide === 'owner' ? 'pending_sportgo_signature' : $document->status),
            'final_file_path' => $this->checkAllSigned($document) ? ($document->final_file_path ?: $document->generated_file_path) : $document->final_file_path,
            'locked_at' => $document->locked_at ?: now(),
            'completed_at' => $this->checkAllSigned($document) ? now() : $document->completed_at,
        ])->save();

        return $signature;
    }

    public function checkAllSigned(GeneratedDocument $document): bool
    {
        $signedSides = $document->signatures()
            ->where('status', 'signed')
            ->pluck('signer_side')
            ->all();

        return in_array('owner', $signedSides, true) && in_array('sportgo', $signedSides, true);
    }

    public function assertCanDownload(GeneratedDocument $document, User $user, bool $isAdmin = false): void
    {
        if ($isAdmin || $document->owner_id === $user->id) {
            return;
        }

        $applicationId = $document->partner_application_id;
        if ($applicationId && PartnerApplication::query()->whereKey($applicationId)->where('user_id', $user->id)->exists()) {
            return;
        }

        abort(403, 'Bạn không có quyền tải văn bản này.');
    }

    public function downloadPath(GeneratedDocument $document): string
    {
        $path = $document->final_file_path ?: $document->generated_file_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Không tìm thấy file văn bản.');
        }

        return $path;
    }

    private function activeTemplate(string $documentType): DocumentTemplate
    {
        $template = DocumentTemplate::query()
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first();

        if ($template) {
            return $template;
        }

        $sourcePath = $this->fallbackTemplatePath($documentType);
        if (! $sourcePath || ! is_file($sourcePath)) {
            throw new RuntimeException("Không tìm thấy template DOCX cho {$documentType}.");
        }

        $fileName = $documentType . '_v1.docx';
        $filePath = 'document-templates/' . $fileName;
        Storage::disk('local')->put($filePath, file_get_contents($sourcePath));

        return DocumentTemplate::updateOrCreate(
            ['document_type' => $documentType, 'version' => 1],
            [
                'template_code' => Str::upper($documentType) . '_V1',
                'template_name' => $this->defaultTitle($documentType, []),
                'file_name' => $fileName,
                'file_path' => $filePath,
                'output_format' => 'docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'storage_disk' => 'local',
                'template_variables' => [],
                'required_fields' => [],
                'render_engine' => 'docx_placeholder',
                'status' => 'active',
                'is_active' => true,
                'activated_at' => now(),
            ]
        );
    }

    private function fallbackTemplatePath(string $documentType): ?string
    {
        $fileName = self::TEMPLATE_FALLBACKS[$documentType] ?? null;

        return $fileName ? base_path('database/seeders/templates/partner-documents/' . $fileName) : null;
    }

    private function replaceDocxPlaceholders(string $docxPath, array $data): void
    {
        if (! class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! str_starts_with($entry, 'word/') || ! str_ends_with($entry, '.xml')) {
                continue;
            }

            $xml = $zip->getFromName($entry);
            if ($xml === false) {
                continue;
            }

            $replaced = $xml;
            foreach ($data as $key => $value) {
                $text = $this->stringValue($value);
                $replaced = str_replace('{{' . $key . '}}', $text, $replaced);
                $replaced = str_replace('{{ ' . $key . ' }}', $text, $replaced);
            }

            if ($replaced !== $xml) {
                $zip->addFromString($entry, $replaced);
            }
        }

        $zip->close();
    }

    private function storeSignatureImage(GeneratedDocumentSignature $signature, string $signatureImage): Media
    {
        $binary = $this->decodeSignatureImage($signatureImage);
        $filePath = 'partner-signatures/' . now()->format('Y/m') . '/' . Str::uuid() . '.png';

        Storage::disk('public')->put($filePath, $binary);

        return Media::create([
            'mediable_type' => GeneratedDocumentSignature::class,
            'mediable_id' => $signature->id,
            'collection' => 'partner_signature',
            'file_name' => basename($filePath),
            'file_path' => $filePath,
            'mime_type' => 'image/png',
            'file_size' => strlen($binary),
        ]);
    }

    private function decodeSignatureImage(string $signatureImage): string
    {
        if (str_contains($signatureImage, ',')) {
            [, $signatureImage] = explode(',', $signatureImage, 2);
        }

        $binary = base64_decode($signatureImage, true);
        if ($binary === false) {
            throw new RuntimeException('Chữ ký không đúng định dạng base64.');
        }

        return $binary;
    }

    private function uniqueDocumentCode(string $documentType): string
    {
        $prefix = self::DOCUMENT_PREFIXES[$documentType] ?? 'DOC';

        do {
            $code = $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (GeneratedDocument::query()->where('document_code', $code)->exists());

        return $code;
    }

    private function defaultTitle(string $documentType, array $renderData): string
    {
        return match ($documentType) {
            'partner_application_form' => 'Đơn đăng ký đối tác ' . ($renderData['venue_name'] ?? ''),
            'partner_contract' => 'Hợp đồng hợp tác ' . ($renderData['venue_name'] ?? ''),
            'termination_request' => 'Đơn yêu cầu chấm dứt hợp tác',
            'mutual_liquidation_minutes' => 'Biên bản thanh lý hợp đồng',
            'unilateral_termination_notice' => 'Công văn chấm dứt hợp đồng',
            'settlement_minutes' => 'Biên bản quyết toán chấm dứt hợp tác',
            default => 'Văn bản đối tác',
        };
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value));
        }

        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
