<?php

namespace App\Services\Partner;

use App\Models\GeneratedDocument;
use App\Models\GeneratedDocumentSignature;
use App\Models\DocumentTemplate;
use App\Models\Media;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Throwable;
use ZipArchive;

class PartnerDocumentService
{
    private const TEMPLATE_FALLBACKS = [
        'partner_application_form' => 'Mau_01_Don_de_nghi_dang_ky_doi_tac_chu_san_SportGo_DA_SUA.docx',
        'partner_contract' => 'Mau_02_Hop_dong_hop_tac_doi_tac_SportGo_DA_SUA.docx',
        'termination_request' => 'Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo_DA_SUA.docx',
        'mutual_liquidation_minutes' => 'Mau_04_Bien_ban_thanh_ly_hop_dong_hai_ben_SportGo_DA_SUA.docx',
        'unilateral_termination_notice' => 'Mau_05_Cong_van_cham_dut_hop_dong_don_phuong_SportGo_DA_SUA.docx',
        'settlement_minutes' => 'Mau_06_Bien_ban_quyet_toan_cham_dut_hop_tac_SportGo_DA_SUA.docx',
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
        $referenceType = $context['reference_type'] ?? $reference::class;
        $referenceId = $context['reference_id'] ?? (string) $reference->getKey();
        $documentVersion = $this->nextDocumentVersion($documentType, $referenceType, $referenceId);

        $sourcePath = $template ? Storage::disk($template->storage_disk)->path($template->file_path) : null;
        if (! $sourcePath || ! is_file($sourcePath)) {
            $sourcePath = $this->fallbackTemplatePath($documentType);
        }

        if (! $sourcePath || ! is_file($sourcePath)) {
            throw new RuntimeException("Không tìm thấy template DOCX cho {$documentType}.");
        }

        $targetPath = Storage::disk('local')->path($filePath);
        $this->ensureLocalDirectory($targetPath);
        $this->renderDocxTemplate($sourcePath, $targetPath, $renderData, $documentType);

        $document = GeneratedDocument::create([
            'document_code' => $documentCode,
            'document_type' => $documentType,
            'template_id' => $template?->id,
            'template_version' => $template?->version ?? 1,
            'document_version' => $documentVersion,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
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
                'signature_method' => $context['signature_method'] ?? ($signatureImage ? 'drawn' : 'typed_confirm'),
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
            
            // Inject signature into DOCX
            try {
                $filePath = Storage::disk('local')->path($document->generated_file_path);
                if (file_exists($filePath)) {
                    $processor = new \PhpOffice\PhpWord\TemplateProcessor($filePath);
                    $processor->setMacroChars('{{', '}}');
                    $placeholder = 'signature_' . $signerSide;
                    $processor->setImageValue($placeholder, [
                        'path' => Storage::disk('public')->path($media->file_path),
                        'width' => 150,
                        'height' => 75,
                        'ratio' => false,
                    ]);
                    $processor->saveAs($filePath);
                    $document->forceFill([
                        'file_hash' => hash_file('sha256', $filePath),
                    ])->save();
                }
            } catch (\Throwable $e) {
                Log::error('Failed to inject partner document signature image.', [
                    'document_id' => $document->id,
                    'signer_side' => $signerSide,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $isCompleted = $this->checkAllSigned($document->refresh());
        $document->forceFill([
            'status' => $isCompleted ? 'completed' : $this->nextStatusAfterSignature($document, $signerSide),
            'final_file_path' => $isCompleted ? ($document->final_file_path ?: $document->generated_file_path) : $document->final_file_path,
            'locked_at' => $document->locked_at ?: now(),
            'completed_at' => $isCompleted ? now() : $document->completed_at,
        ])->save();

        return $signature;
    }

    public function checkAllSigned(GeneratedDocument $document): bool
    {
        $signedSides = $document->signatures()
            ->where('status', 'signed')
            ->pluck('signer_side')
            ->all();

        if ($document->document_type === 'partner_application_form') {
            return in_array('owner', $signedSides, true);
        }

        return in_array('owner', $signedSides, true) && in_array('sportgo', $signedSides, true);
    }

    private function nextStatusAfterSignature(GeneratedDocument $document, string $signerSide): string
    {
        if ($document->document_type === 'partner_contract') {
            return $signerSide === 'sportgo' ? 'pending_owner_signature' : 'pending_sportgo_signature';
        }

        if ($document->document_type === 'partner_application_form' && $signerSide === 'owner') {
            return 'completed';
        }

        return $document->status;
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

    private function ensureLocalDirectory(string $targetPath): void
    {
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    private function renderDocxTemplate(string $sourcePath, string $targetPath, array $data, string $documentType): void
    {
        $tempPath = $targetPath . '.tmp.docx';
        copy($sourcePath, $tempPath);
        $this->fixSplitMacrosInDocx($tempPath);

        try {
            $previousEscaping = Settings::isOutputEscapingEnabled();
            Settings::setOutputEscapingEnabled(true);

            try {
                $processor = new TemplateProcessor($tempPath);
                $processor->setMacroChars('{{', '}}');

                foreach ($data as $key => $value) {
                    $text = $this->plainValue($value);
                    $processor->setValue($key, $text);
                    $processor->setValue(' ' . $key . ' ', $text);
                }

                $processor->saveAs($targetPath);
            } finally {
                Settings::setOutputEscapingEnabled($previousEscaping);
            }
        } catch (Throwable) {
            copy($tempPath, $targetPath);
            $this->replaceDocxPlaceholders($targetPath, $data, $documentType);
        }

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        $this->appendDocumentDataAppendixToFile($targetPath, $data, $documentType);
    }

    private function fixSplitMacrosInDocx(string $docxPath): void
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

            // Remove internal xml tags that split our macros {{ ... }}
            // We match something like: {<tags>{var_name}<tags>}
            $replaced = preg_replace_callback('/\{(<[^>]+>)*\{.*?\}(<[^>]+>)*\}/s', function ($matches) {
                $macro = trim(strip_tags($matches[0]));
                $macro = trim($macro, "{} \t\n\r\0\x0B");

                return '{{' . $macro . '}}';
            }, $xml);

            if ($replaced !== $xml) {
                $zip->addFromString($entry, $replaced);
            }
        }

        $zip->close();
    }

    private function appendDocumentDataAppendixToFile(string $docxPath, array $data, string $documentType): void
    {
        if (! in_array($documentType, ['partner_application_form', 'partner_contract'], true) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $this->appendDocumentDataAppendix($zip, $data, $documentType);
        $zip->close();
    }

    private function replaceDocxPlaceholders(string $docxPath, array $data, string $documentType): void
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

    private function appendDocumentDataAppendix(ZipArchive $zip, array $data, string $documentType): void
    {
        match ($documentType) {
            'partner_application_form' => $this->appendApplicationAppendix($zip, $data),
            'partner_contract' => $this->appendPartnerContractAppendix($zip, $data),
            default => null,
        };
    }

    private function appendApplicationAppendix(ZipArchive $zip, array $data): void
    {
        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false || ! str_contains($xml, '</w:body>')) {
            return;
        }

        $rows = [
            ['Người đăng ký', $data['applicant_full_name'] ?? $data['full_name'] ?? null],
            ['Ngày sinh', $data['applicant_birth_date'] ?? null],
            ['Điện thoại', $data['applicant_phone'] ?? $data['phone'] ?? null],
            ['Email', $data['applicant_email'] ?? $data['email'] ?? null],
            ['Địa chỉ liên hệ', $data['applicant_address'] ?? null],
            ['Số giấy tờ', $data['representative_identity_number'] ?? $data['id_number'] ?? null],
            ['Đơn vị kinh doanh', $data['business_name'] ?? null],
            ['Mã số thuế', $data['tax_code'] ?? null],
            ['Số giấy đăng ký', $data['business_license_number'] ?? null],
            ['Tên cụm sân', $data['venue_name'] ?? null],
            ['Địa chỉ cụm sân', $data['venue_address'] ?? null],
            ['Tỉnh/Thành phố', $data['venue_province'] ?? null],
            ['Phường/Xã', $data['venue_ward'] ?? null],
            ['Google Maps', $data['venue_map_url'] ?? null],
            ['Tọa độ', trim(($data['venue_latitude'] ?? '') . ', ' . ($data['venue_longitude'] ?? ''), ', ')],
            ['Số lượng sân con', $data['court_count_total'] ?? $data['court_count'] ?? null],
            ['Giá cơ bản/giờ của cụm sân', $data['base_price_per_hour_label'] ?? null],
            ['Danh sách sân con', $data['courts_summary'] ?? null],
            ['Ngân hàng', $data['bank_name'] ?? null],
            ['Số tài khoản', $data['account_number'] ?? null],
            ['Chủ tài khoản', $data['account_holder_name'] ?? null],
            ['Trạng thái xác minh ngân hàng', $data['bank_verification_label'] ?? $data['bank_verification_status'] ?? null],
            ['Thời điểm xác minh ngân hàng', $data['bank_verified_at'] ?? null],
            ['Tài liệu đính kèm', $data['attachments'] ?? null],
        ];

        $paragraphs = [
            $this->docxParagraph(''),
            $this->docxParagraph('PHỤ LỤC THÔNG TIN ĐĂNG KÝ ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO', true),
            $this->docxParagraph('Phần này được hệ thống tự động điền từ dữ liệu người dùng nhập trước khi gửi hồ sơ.'),
        ];

        foreach ($rows as [$label, $value]) {
            if ($value === null || $value === '') {
                continue;
            }

            $paragraphs[] = $this->docxParagraph($label . ': ' . $this->plainValue($value));
        }

        $paragraphs[] = $this->docxParagraph('');
        $paragraphs[] = $this->docxParagraph('Chữ ký người đăng ký/chủ sân:', true);
        $paragraphs[] = $this->docxParagraph('{{signature_owner}}');

        $insert = implode('', $paragraphs);
        $xml = str_replace('</w:body>', $insert . '</w:body>', $xml);
        $zip->addFromString($entry, $xml);
    }

    private function appendPartnerContractAppendix(ZipArchive $zip, array $data): void
    {
        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false || ! str_contains($xml, '</w:body>')) {
            return;
        }

        $rows = [
            ['Số hợp đồng', $data['contract_number'] ?? $data['contract_code'] ?? null],
            ['Ngày lập hợp đồng', $data['signed_date'] ?? null],
            ['Tên văn bản', $data['contract_title'] ?? null],
            ['Bên A', $data['sportgo_company_name'] ?? null],
            ['Mã số thuế/ĐKKD Bên A', $data['sportgo_tax_code'] ?? null],
            ['Địa chỉ Bên A', $data['sportgo_address'] ?? null],
            ['Đại diện Bên A', $data['sportgo_representative_name'] ?? null],
            ['Chức vụ Bên A', $data['sportgo_representative_title'] ?? null],
            ['Bên B', $data['party_b_name'] ?? $data['business_name'] ?? null],
            ['CCCD/MST/ĐKKD Bên B', $data['party_b_id'] ?? $data['tax_code'] ?? $data['identity_number'] ?? null],
            ['Địa chỉ Bên B', $data['party_b_address'] ?? $data['venue_address'] ?? null],
            ['Người đại diện/chủ tài khoản', $data['owner_full_name'] ?? $data['owner_signer_full_name'] ?? null],
            ['Điện thoại chủ sân', $data['owner_phone'] ?? null],
            ['Email chủ sân', $data['owner_email'] ?? null],
            ['Cụm sân hợp tác', $data['venue_cluster_list'] ?? $data['venue_name'] ?? null],
            ['Địa chỉ cụm sân', $data['venue_address'] ?? null],
            ['Loại sân', $data['court_types_summary'] ?? null],
            ['Ngân hàng', $data['bank_name'] ?? null],
            ['Số tài khoản', $data['account_number'] ?? null],
            ['Thời hạn hợp đồng', $data['contract_duration'] ?? null],
            ['Hiệu lực từ', $data['effective_from'] ?? $data['contract_start_date'] ?? null],
            ['Hiệu lực đến', $data['effective_to'] ?? null],
            ['Phí nền tảng', $data['platform_fee_amount'] ?? null],
            ['Quy định thanh toán', $data['payment_due_rule'] ?? null],
            ['Quy định khóa quá hạn', $data['overdue_lock_rule'] ?? null],
            ['Chính sách hoàn phí', $data['refund_policy_summary'] ?? null],
        ];

        $paragraphs = [
            $this->docxParagraph(''),
            $this->docxParagraph('PHỤ LỤC THÔNG TIN HỢP ĐỒNG ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO', true),
            $this->docxParagraph('Phần này được hệ thống tự động điền từ hồ sơ đối tác đã được duyệt để bảo đảm file Word lưu, tải và preview có dữ liệu thật.'),
        ];

        foreach ($rows as [$label, $value]) {
            if ($value === null || $value === '') {
                continue;
            }

            $paragraphs[] = $this->docxParagraph($label . ': ' . $this->plainValue($value));
        }

        $paragraphs[] = $this->docxParagraph('');
        $paragraphs[] = $this->docxParagraph('Chữ ký đại diện SportGo:', true);
        $paragraphs[] = $this->docxParagraph('{{signature_sportgo}}');
        $paragraphs[] = $this->docxParagraph('Chữ ký đối tác/chủ sân:', true);
        $paragraphs[] = $this->docxParagraph('{{signature_owner}}');

        $insert = implode('', $paragraphs);
        $xml = str_replace('</w:body>', $insert . '</w:body>', $xml);
        $zip->addFromString($entry, $xml);
    }

    private function docxParagraph(string $text, bool $bold = false): string
    {
        $boldXml = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        $safeText = htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return '<w:p><w:r>' . $boldXml . '<w:t xml:space="preserve">' . $safeText . '</w:t></w:r></w:p>';
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

    private function nextDocumentVersion(string $documentType, string $referenceType, string $referenceId): int
    {
        $latest = GeneratedDocument::query()
            ->where('document_type', $documentType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->max('document_version');

        return ((int) $latest) + 1;
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
        return htmlspecialchars($this->plainValue($value), ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function plainValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value));
        }

        return (string) $value;
    }
}
