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
        $referenceType = $context['reference_type'] ?? $reference::class;
        $referenceId = $context['reference_id'] ?? (string) $reference->getKey();
        $documentVersion = $this->nextDocumentVersion($documentType, $referenceType, $referenceId);
        $renderedAt = now();
        $renderData = array_merge($renderData, [
            'document_code' => $documentCode,
            'document_version' => $documentVersion,
            'rendered_at' => $renderedAt->format('d/m/Y H:i'),
            'rendered_by' => $actor?->full_name ?? $actor?->username ?? $actor?->email ?? 'Hệ thống',
        ]);
        $filePath = 'generated-documents/' . $renderedAt->format('Y/m') . '/' . $documentCode . '.docx';
        $title = $context['title'] ?? $this->defaultTitle($documentType, $renderData);

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
            'generated_at' => $renderedAt,
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
                        'width' => 120,
                        'height' => 55,
                        'ratio' => false,
                    ]);
                    $processor->setValue($signerSide . '_signer_name', $signature->signer_full_name);
                    $processor->saveAs($filePath);
                    $this->polishSignedDocumentFile($document->fresh());
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

        if (! $signatureImage) {
            $this->polishSignedDocumentFile($document->fresh());
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

        $this->applyDocxRegexReplacements($targetPath, $data, $documentType);

        $this->fillKnownTemplateBodyFields($targetPath, $data, $documentType);
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
        // Phụ lục đã bị vô hiệu hóa theo yêu cầu của hệ thống (không chèn notes/phụ lục)
        return;
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

    private function applyDocxRegexReplacements(string $docxPath, array $data, string $documentType): void
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

            // Kính gửi
            $replaced = preg_replace('/Kính gửi:\s*(?:<[^>]+>)*\[Tên công ty\/đơn vị vận hành nền tảng SportGo\]/u', 'Kính gửi: Công ty TNHH SportGo', $replaced);
            $replaced = preg_replace('/Kính gửi:\s*(?:<[^>]+>)*\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\./u', 'Kính gửi: Công ty TNHH SportGo', $replaced);

            // Auto-fill contract number and date if they use dots instead of placeholders
            if (isset($data['contract_code'])) {
                $replaced = preg_replace('/(Số:\s*(?:<[^>]+>)*)[\. \t]+((?:<[^>]+>)*\/HĐHT-SG)/u', '${1}' . $data['contract_code'] . '${2}', $replaced);
            }
            if (isset($data['location_date'])) {
                // Thay vì replace cả ngày tháng năm, chúng ta tìm và replace một cách an toàn
                $replaced = preg_replace('/[\. \t_]*(?:<[^>]+>)*,?\s*(?:<[^>]+>)*ngày\s*(?:<[^>]+>)*[\. \t_]+(?:<[^>]+>)*tháng\s*(?:<[^>]+>)*[\. \t_]+(?:<[^>]+>)*năm\s*(?:<[^>]+>)*[\. \t_]+/u', $data['location_date'], $replaced);
            }

            if ($replaced !== $xml) {
                $zip->addFromString($entry, $replaced);
            }
        }

        $zip->close();
    }

    private function fillKnownTemplateBodyFields(string $docxPath, array $data, string $documentType): void
    {
        $fields = match ($documentType) {
            'partner_application_form' => $this->applicationTemplateBodyValues($data),
            'partner_contract' => $this->partnerContractTemplateValues($data),
            default => [],
        };

        if ($fields === []) {
            return;
        }

        $this->fillTwoColumnTemplateBodyFields($docxPath, $fields);
        $this->fillKnownTemplateInlineText($docxPath, $data, $documentType);
        $this->ensureDocumentSignaturePlaceholders($docxPath, $documentType);
    }

    private function ensureDocumentSignaturePlaceholders(string $docxPath, string $documentType): void
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false) {
            $zip->close();
            return;
        }

        $dom = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            $zip->close();
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $changed = false;
        if ($documentType === 'partner_application_form') {
            foreach ($xpath->query('//w:p') as $paragraph) {
                $ascii = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($paragraph, $xpath)));
                if (str_contains($ascii, 'kyghirohoten')) {
                    if (! str_contains($xml, '{{signature_owner}}')) {
                        $changed = $this->insertDocxParagraphAfter($paragraph, '{{signature_owner}}') || $changed;
                    }
                    break;
                }
            }

            foreach ($xpath->query('//w:p') as $paragraph) {
                $ascii = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($paragraph, $xpath)));
                if ($ascii === 'hovaten') {
                    $changed = $this->replaceDocxCellText($paragraph, $xpath, 'Họ và tên: {{owner_signer_name}}') || $changed;
                    break;
                }
            }
        }

        if ($documentType === 'partner_contract') {
            $tables = $xpath->query('//w:tbl');
            $signatureTable = $tables->item(max(0, $tables->length - 1));
            if ($signatureTable) {
                $changed = $this->ensureDocxTableBorders($signatureTable) || $changed;
                $changed = $this->centerDocxTableParagraphs($signatureTable, $xpath) || $changed;
                $rows = $xpath->query('./w:tr', $signatureTable);
                $targetRow = $rows->item(2) ?: $rows->item($rows->length - 1);
                if ($targetRow) {
                    $cells = $xpath->query('./w:tc', $targetRow);
                    if ($cells->length >= 2) {
                        if (! str_contains($this->docxNodeText($cells->item(0), $xpath), 'signature_sportgo')) {
                            $changed = $this->replaceDocxCellText($cells->item(0), $xpath, '{{signature_sportgo}}') || $changed;
                        }
                        if (! str_contains($this->docxNodeText($cells->item(1), $xpath), 'signature_owner')) {
                            $changed = $this->replaceDocxCellText($cells->item(1), $xpath, '{{signature_owner}}') || $changed;
                        }
                    }
                }

                $nameRow = $rows->item(3);
                if ($nameRow) {
                    $cells = $xpath->query('./w:tc', $nameRow);
                    if ($cells->length >= 2) {
                        $changed = $this->replaceDocxCellText($cells->item(0), $xpath, 'Họ và tên: {{sportgo_signer_name}}') || $changed;
                        $changed = $this->replaceDocxCellText($cells->item(1), $xpath, 'Họ và tên: {{owner_signer_name}}') || $changed;
                    }
                }
            }
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function fillKnownTemplateInlineText(string $docxPath, array $data, string $documentType): void
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false) {
            $zip->close();
            return;
        }

        $dom = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            $zip->close();
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $changed = false;
        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = $this->docxNodeText($paragraph, $xpath);
            if ($text === '') {
                continue;
            }

            $replacement = $this->inlineReplacementText($text, $data, $documentType);
            if ($replacement !== null && $replacement !== $text) {
                $changed = $this->replaceDocxCellText($paragraph, $xpath, $replacement) || $changed;
            }
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function inlineReplacementText(string $text, array $data, string $documentType): ?string
    {
        $normalized = $this->normalizeDocxLabel($text);
        $ascii = Str::ascii($normalized);
        $place = $this->firstFilled($data, ['document_place', 'venue_province']) ?: 'Hà Nội';
        [$day, $month, $year] = $this->documentDateParts($data);

        if (str_contains($ascii, 'ngay') && str_contains($ascii, 'thang') && str_contains($ascii, 'nam')) {
            return "{$place}, ngày {$day} tháng {$month} năm {$year}";
        }

        if ($documentType === 'partner_application_form') {
            if (str_contains($ascii, 'kinhgui')) {
                return 'Kính gửi: Công ty/đơn vị vận hành nền tảng SportGo';
            }
        }

        if ($documentType === 'partner_contract') {
            if (str_starts_with($ascii, 'so') && str_contains($ascii, 'hdhtsg')) {
                $contractCode = $this->firstFilled($data, ['contract_code', 'contract_number', 'document_code']) ?: 'HDHT-SG';
                return "Số: {$contractCode}/HĐHT-SG";
            }

            if (str_contains($ascii, 'homnaytai')) {
                $address = $this->firstFilled($data, ['sportgo_address']) ?: $place;
                return "Hôm nay, tại {$address}, các bên thống nhất ký kết Hợp đồng hợp tác đối tác SportGo với các nội dung sau:";
            }
        }

        return null;
    }

    private function documentDateParts(array $data): array
    {
        $value = $this->firstFilled($data, ['signed_date', 'submitted_at', 'rendered_at']) ?: now()->format('d/m/Y');

        try {
            $date = \Carbon\Carbon::parse(str_replace('/', '-', $value));
        } catch (Throwable) {
            $date = now();
        }

        return [$date->format('d'), $date->format('m'), $date->format('Y')];
    }

    /**
     * Some official templates use dotted blanks instead of {{placeholders}}.
     * Fill the value cell by matching the left label cell in two-column tables.
     *
     * @param  array<string, mixed>  $fields
     */
    private function fillTwoColumnTemplateBodyFields(string $docxPath, array $fields): void
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
            return;
        }

        $fieldGroups = $this->normalizeDocxFieldGroups($fields);
        if ($fieldGroups === []) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false) {
            $zip->close();
            return;
        }

        $dom = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            $zip->close();
            return;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $changed = false;
        $tableIndex = 0;
        foreach ($xpath->query('//w:tbl') as $table) {
            $tableIndex++;
            $normalizedFields = array_replace($fieldGroups[0] ?? [], $fieldGroups[$tableIndex] ?? []);
            if ($normalizedFields === []) {
                continue;
            }

            foreach ($xpath->query('./w:tr', $table) as $row) {
                $cells = $xpath->query('./w:tc', $row);
                if ($cells->length < 2) {
                    continue;
                }

                $label = $this->normalizeDocxLabel($this->docxNodeText($cells->item(0), $xpath));
                if ($label === '') {
                    continue;
                }

                foreach ($normalizedFields as $needle => $value) {
                    if ($needle !== '' && str_contains($label, $needle)) {
                        $changed = $this->replaceDocxCellText($cells->item(1), $xpath, $value) || $changed;
                        break;
                    }
                }
            }
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function normalizeDocxFieldGroups(array $fields): array
    {
        $groups = [];

        foreach ($fields as $group => $values) {
            if (is_int($group) && is_array($values)) {
                foreach ($values as $label => $value) {
                    $text = $this->cellPlainValue($value);
                    if ($text !== '') {
                        $groups[$group][$this->normalizeDocxLabel((string) $label)] = $text;
                    }
                }

                continue;
            }

            $text = $this->cellPlainValue($values);
            if ($text !== '') {
                $groups[0][$this->normalizeDocxLabel((string) $group)] = $text;
            }
        }

        return $groups;
    }

    private function applicationTemplateBodyValues(array $data): array
    {
        $applicantName = $this->firstFilled($data, ['applicant_full_name', 'full_name']);
        $businessName = $this->firstFilled($data, ['business_name']);
        $email = $this->firstFilled($data, ['applicant_email', 'email', 'venue_email']);
        $phone = $this->firstFilled($data, ['applicant_phone', 'phone']);
        $identityNumber = $this->firstFilled($data, ['representative_identity_number', 'id_number']);
        $legalNumbers = $this->joinFilled([
            $identityNumber,
            $this->firstFilled($data, ['tax_code']),
            $this->firstFilled($data, ['business_license_number', 'business_code']),
        ], '; ');

        return [
            'Tên đơn vị' => 'Công ty TNHH SportGo',
            'Mã số thuế/ĐKKD' => '0000000000',
            'Địa chỉ trụ sở' => 'Tòa P cao đẳng FPT Polytechnic Đường Phan Tây Nhạc, Phường Xuân Phương, Hà Nội',
            'Người đại diện' => 'Nguyễn Đức Kiên',
            'Chức vụ' => 'Giám đốc',
            'Căn cứ đại diện/ủy quyền' => 'Người đại diện theo pháp luật',
            'Số điện thoại/Email' => 'contact@sportgo.vn',
            'Tài khoản thu phí/hoàn trả nếu có' => 'Không có',
            'Mã hồ sơ đăng ký' => $this->firstFilled($data, ['application_code', 'document_code']),
            'Loại người đề nghị' => $this->applicantTypeLabel($this->firstFilled($data, ['applicant_type'])),
            'Họ tên/Tên tổ chức' => $businessName ?: $applicantName,
            'Số CCCD/CMND/Hộ chiếu/MST/ĐKKD' => $legalNumbers,
            'Ngày cấp - Nơi cấp' => $this->issuedInfo($data),
            'Người đại diện hợp pháp' => $this->firstFilled($data, ['representative_name']) ?: $applicantName,
            'Chức vụ/Quan hệ đại diện' => $this->firstFilled($data, ['representative_position', 'business_representative_position']) ?: ($businessName ? 'Người đại diện' : 'Chủ cơ sở'),
            'Số điện thoại liên hệ' => $phone,
            'Email liên hệ' => $email,
            'Địa chỉ thường trú/trụ sở/liên hệ' => $this->joinFilled([
                $this->firstFilled($data, ['applicant_address']),
                $this->firstFilled($data, ['business_address']),
            ], ' | '),
            'Tài khoản đăng nhập SportGo dự kiến' => $email,
            'Tên cụm sân dự kiến hiển thị' => $this->firstFilled($data, ['venue_name']),
            'Mã cụm sân trên hệ thống nếu đã có' => $this->firstFilled($data, ['venue_cluster_code', 'venue_cluster_id', 'approved_venue_cluster_id']) ?: 'Chưa có',
            'Địa chỉ cụm sân' => $this->joinFilled([
                $this->firstFilled($data, ['venue_address']),
                $this->firstFilled($data, ['venue_ward']),
                $this->firstFilled($data, ['venue_province']),
            ]),
            'Tọa độ/đường dẫn bản đồ' => $this->coordinatesAndMap($data),
            'Người quản lý trực tiếp tại sân' => $this->firstFilled($data, ['venue_manager_name', 'representative_name', 'applicant_full_name', 'full_name']),
            'Số điện thoại liên hệ tại sân' => $this->firstFilled($data, ['venue_phone', 'applicant_phone', 'phone']),
            'Loại sân/môn thể thao kinh doanh' => $this->firstFilled($data, ['court_types', 'court_types_summary', 'courts_summary']),
            'Số lượng sân con dự kiến' => $this->firstFilled($data, ['court_count_total', 'court_count']),
            'Thời gian hoạt động dự kiến' => $this->firstFilled($data, ['expected_opening_hours']),
            'Tiện ích, dịch vụ đi kèm' => $this->firstFilled($data, ['amenities']),
            'Mô tả ngắn về cụm sân' => $this->joinFilled([
                $this->firstFilled($data, ['venue_description']),
                $this->firstFilled($data, ['courts_summary']) ? 'Danh sách sân con: ' . $this->firstFilled($data, ['courts_summary']) : null,
            ], ' | '),
            'Tư cách pháp lý của người đề nghị' => $this->applicantTypeLabel($this->firstFilled($data, ['applicant_type'])),
            'Căn cứ quyền sử dụng/khai thác mặt bằng' => $this->firstFilled($data, ['premises_basis', 'land_use_basis']) ?: 'Hồ sơ mặt bằng đã tải lên trong phụ lục',
            'Thời hạn quyền sử dụng/khai thác' => $this->firstFilled($data, ['premises_usage_term', 'land_use_term']) ?: 'Theo hồ sơ đính kèm',
            'Giấy tờ kinh doanh liên quan' => $this->joinFilled([
                $this->firstFilled($data, ['business_license_number', 'business_code']),
                $this->firstFilled($data, ['tax_code']),
            ], '; ') ?: 'Theo hồ sơ đính kèm',
            'Giấy tờ/giấy phép khác nếu pháp luật yêu cầu' => $this->firstFilled($data, ['additional_licenses', 'attachments']) ?: 'Theo hồ sơ đính kèm',
            'Tình trạng tranh chấp/hạn chế pháp lý của mặt bằng' => $this->firstFilled($data, ['legal_dispute_status']) ?: 'Người đăng ký cam kết không có tranh chấp/hạn chế pháp lý chưa khai báo',
            'Tên ngân hàng' => $this->firstFilled($data, ['bank_name']),
            'Số tài khoản' => $this->firstFilled($data, ['account_number']),
            'Tên chủ tài khoản' => $this->firstFilled($data, ['account_holder_name']),
            'Chi nhánh/ngân hàng liên quan nếu có' => $this->firstFilled($data, ['bank_branch']) ?: 'Không có',
            'Tài liệu xác minh tài khoản nhận tiền' => $this->firstFilled($data, ['bank_verification_label', 'bank_verification_status']) ?: 'Chứng từ ngân hàng đã tải lên',
            'Ngày tiếp nhận hồ sơ' => $this->firstFilled($data, ['submitted_at', 'rendered_at']),
            'Người tiếp nhận' => 'Hệ thống SportGo',
            'Tình trạng hồ sơ' => $this->firstFilled($data, ['application_status_label', 'status_label']) ?: 'Chờ ký/nộp hồ sơ',
            'Tài liệu cần bổ sung nếu có' => $this->firstFilled($data, ['supplement_required']) ?: 'Chưa có',
            'Kết quả xử lý' => $this->firstFilled($data, ['review_result']) ?: 'Chờ thẩm định',
        ];
    }

    private function partnerContractTemplateValues(array $data): array
    {
        $applicantName = $this->firstFilled($data, ['owner_full_name', 'owner_signer_full_name', 'representative_name', 'party_b_name', 'business_name']);
        $businessName = $this->firstFilled($data, ['business_name']);
        $email = $this->firstFilled($data, ['owner_email']);
        $phone = $this->firstFilled($data, ['owner_phone']);

        $legalNumbers = $this->joinFilled([
            $this->firstFilled($data, ['party_b_id', 'identity_number']),
            $this->firstFilled($data, ['tax_code']),
            $this->firstFilled($data, ['business_license_number', 'business_code']),
        ], '; ');

        return [
            1 => [
                'Tên đơn vị' => $this->firstFilled($data, ['sportgo_company_name']) ?: 'Công ty TNHH SportGo',
                'Mã số thuế/ĐKKD' => $this->firstFilled($data, ['sportgo_tax_code']) ?: 'SPORTGO',
                'Địa chỉ trụ sở' => $this->firstFilled($data, ['sportgo_address']) ?: config('app.url'),
                'Người đại diện' => $this->firstFilled($data, ['sportgo_representative_name']) ?: 'Đại diện SportGo',
                'Chức vụ' => $this->firstFilled($data, ['sportgo_representative_title']) ?: 'Đại diện pháp lý',
                'Căn cứ đại diện/ủy quyền' => $this->firstFilled($data, ['sportgo_authorization_basis']) ?: 'Theo phân quyền nội bộ SportGo',
                'Số điện thoại/Email' => $this->joinFilled([
                    $this->firstFilled($data, ['sportgo_phone']),
                    $this->firstFilled($data, ['sportgo_email']) ?: config('mail.from.address'),
                ], ' - '),
                'Tài khoản thu phí/hoàn trả nếu có' => $this->firstFilled($data, ['sportgo_bank_account']) ?: 'Tài khoản SportGo trên hệ thống thanh toán trung gian',
            ],
            2 => [
                'Họ tên/Tên tổ chức' => $businessName ?: $applicantName,
                'Số CCCD/CMND/Hộ chiếu/MST/ĐKKD' => $legalNumbers,
                'Ngày cấp - Nơi cấp' => $this->issuedInfo($data),
                'Địa chỉ liên hệ/trụ sở' => $this->firstFilled($data, ['party_b_address', 'business_address', 'venue_address']),
                'Người đại diện nếu là tổ chức' => $businessName ? $applicantName : 'Không áp dụng',
                'Chức vụ/Quan hệ đại diện' => $this->firstFilled($data, ['representative_position']) ?: ($businessName ? 'Người đại diện' : 'Chủ cơ sở'),
                'Số điện thoại/Email' => $this->joinFilled([$phone, $email], ' - '),
                'Tài khoản nhận tiền' => $this->joinFilled([
                    $this->firstFilled($data, ['bank_name']),
                    $this->firstFilled($data, ['account_number']),
                    $this->firstFilled($data, ['account_holder_name']),
                ], ' - '),
            ],
            3 => [
                'Tên cụm sân' => $this->firstFilled($data, ['venue_name', 'venue_cluster_list']),
                'Mã cụm sân trên hệ thống' => $this->firstFilled($data, ['venue_cluster_code', 'venue_cluster_id']) ?: 'Tạo sau khi hợp đồng hoàn tất',
                'Địa chỉ cụm sân' => $this->firstFilled($data, ['venue_address']),
                'Loại sân/môn thể thao' => $this->firstFilled($data, ['court_types_summary', 'court_types']),
                'Số lượng sân con' => $this->firstFilled($data, ['court_count_total', 'court_count']),
                'Thời gian hoạt động' => $this->firstFilled($data, ['expected_opening_hours']) ?: 'Theo cấu hình vận hành trên SportGo',
            ],
        ];
    }

    private function legacyPartnerContractTemplateValues(array $data): array
    {
        $applicantName = $this->firstFilled($data, ['owner_full_name', 'owner_signer_full_name', 'party_b_name', 'business_name']);
        $businessName = $this->firstFilled($data, ['business_name']);
        $email = $this->firstFilled($data, ['owner_email']);
        $phone = $this->firstFilled($data, ['owner_phone']);
        
        $legalNumbers = $this->joinFilled([
            $this->firstFilled($data, ['party_b_id', 'identity_number']),
            $this->firstFilled($data, ['tax_code']),
            $this->firstFilled($data, ['business_license_number', 'business_code']),
        ], '; ');

        return [
            'Tên đơn vị' => 'Công ty TNHH SportGo',
            'Mã số thuế/ĐKKD' => '0000000000',
            'Địa chỉ trụ sở' => 'Tòa P cao đẳng FPT Polytechnic Đường Phan Tây Nhạc, Phường Xuân Phương, Hà Nội',
            'Người đại diện' => 'Nguyễn Đức Kiên',
            'Chức vụ' => 'Giám đốc',
            'Căn cứ đại diện/ủy quyền' => 'Người đại diện theo pháp luật',
            'Số điện thoại/Email' => 'contact@sportgo.vn',
            'Tài khoản thu phí/hoàn trả nếu có' => 'Tài khoản SportGo trên hệ thống thanh toán trung gian',
            'Họ tên/Tên tổ chức' => $businessName ?: $applicantName,
            'Số CCCD/CMND/Hộ chiếu/MST/ĐKKD' => $legalNumbers,
            'Ngày cấp - Nơi cấp' => $this->issuedInfo($data),
            'Địa chỉ liên hệ/trụ sở' => $this->firstFilled($data, ['party_b_address', 'venue_address']),
            'Người đại diện nếu là tổ chức' => $businessName ? $applicantName : 'Không',
            'Chức vụ/Quan hệ đại diện' => $businessName ? 'Người đại diện' : 'Chủ cơ sở',
            'Số điện thoại' => $phone,
            'Email' => $email,
            'Tài khoản nhận thanh toán' => $this->joinFilled([
                $this->firstFilled($data, ['account_number']),
                $this->firstFilled($data, ['bank_name']),
            ], ' - '),
            'Cụm sân hợp tác' => $this->firstFilled($data, ['venue_cluster_list', 'venue_name']),
            'Địa chỉ cụm sân' => $this->firstFilled($data, ['venue_address']),
            'Số lượng sân con' => $this->firstFilled($data, ['court_count_total', 'court_count']),
            'Quy định khóa quá hạn' => $this->firstFilled($data, ['overdue_lock_rule']),
            'Chính sách hoàn phí' => $this->firstFilled($data, ['refund_policy_summary']),
        ];
    }

    private function docxNodeText(\DOMNode $node, \DOMXPath $xpath): string
    {
        $texts = [];
        foreach ($xpath->query('.//w:t', $node) as $textNode) {
            $texts[] = $textNode->nodeValue ?? '';
        }

        return trim(implode('', $texts));
    }

    private function replaceDocxCellText(\DOMNode $cell, \DOMXPath $xpath, string $text): bool
    {
        $textNodes = $xpath->query('.//w:t', $cell);
        if ($textNodes->length === 0) {
            $document = $cell->ownerDocument;
            if (! $document) {
                return false;
            }

            $paragraph = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
            $run = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
            $textNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
            $textNode->setAttribute('xml:space', 'preserve');
            $textNode->appendChild($document->createTextNode($text));
            $run->appendChild($textNode);
            $paragraph->appendChild($run);
            $cell->appendChild($paragraph);

            return true;
        }

        $isFirst = true;
        foreach ($textNodes as $textNode) {
            $textNode->nodeValue = '';
            if ($isFirst && $text !== '') {
                $textNode->appendChild($textNode->ownerDocument->createTextNode($text));
            }
            $textNode->setAttribute('xml:space', 'preserve');
            $isFirst = false;
        }

        return true;
    }

    private function insertDocxParagraphAfter(\DOMNode $paragraph, string $text): bool
    {
        $document = $paragraph->ownerDocument;
        $parent = $paragraph->parentNode;
        if (! $document || ! $parent) {
            return false;
        }

        $newParagraph = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:p');
        $run = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        $textNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
        $textNode->setAttribute('xml:space', 'preserve');
        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);
        $newParagraph->appendChild($run);

        if ($paragraph->nextSibling) {
            $parent->insertBefore($newParagraph, $paragraph->nextSibling);
        } else {
            $parent->appendChild($newParagraph);
        }

        return true;
    }

    private function normalizeDocxLabel(string $text): string
    {
        $normalized = Str::lower($text);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?: '';
    }

    private function firstFilled(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            $text = $this->cellPlainValue($value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function joinFilled(array $values, string $separator = ', '): ?string
    {
        $items = [];
        foreach ($values as $value) {
            $text = $this->cellPlainValue($value);
            if ($text !== '' && ! in_array($text, $items, true)) {
                $items[] = $text;
            }
        }

        return $items === [] ? null : implode($separator, $items);
    }

    private function cellPlainValue(mixed $value): string
    {
        $text = trim($this->plainValue($value));

        return preg_replace('/\s+/u', ' ', $text) ?: '';
    }

    private function applicantTypeLabel(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        return [
            'individual' => 'Cá nhân',
            'business' => 'Hộ kinh doanh',
            'company' => 'Doanh nghiệp',
            'organization' => 'Tổ chức khác',
        ][$type] ?? $type;
    }

    private function issuedInfo(array $data): ?string
    {
        if ($issuedInfo = $this->firstFilled($data, ['id_issued_info'])) {
            return $issuedInfo;
        }

        return $this->joinFilled([
            $this->formatDateForDocument(data_get($data, 'representative_identity_issued_date')),
            $this->firstFilled($data, ['representative_identity_issued_place']),
        ], ' - ');
    }

    private function coordinatesAndMap(array $data): ?string
    {
        $coordinates = $this->joinFilled([
            $this->firstFilled($data, ['venue_latitude']),
            $this->firstFilled($data, ['venue_longitude']),
        ], ', ');

        return $this->joinFilled([
            $coordinates,
            $this->firstFilled($data, ['venue_map_url']),
        ], ' - ');
    }

    private function formatDateForDocument(mixed $value): ?string
    {
        $text = $this->cellPlainValue($value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $text)) {
            return $text;
        }

        try {
            return \Carbon\Carbon::parse($text)->format('d/m/Y');
        } catch (Throwable) {
            return $text;
        }
    }

    private function appendDocumentDataAppendix(ZipArchive $zip, array $data, string $documentType): void
    {
        match ($documentType) {
            'partner_application_form' => $this->appendApplicationAppendix($zip, $data),
            'partner_contract' => $this->appendPartnerContractAppendix($zip, $data),
            'termination_request',
            'mutual_liquidation_minutes',
            'unilateral_termination_notice',
            'settlement_minutes' => $this->appendWorkflowDocumentAppendix($zip, $data, $documentType),
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
        if (! str_contains($xml, '{{signature_owner}}')) {
            $paragraphs[] = $this->docxParagraph('Chữ ký người đăng ký/chủ sân:', true);
            $paragraphs[] = $this->docxParagraph('{{signature_owner}}');
        }

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
        if (! str_contains($xml, '{{signature_sportgo}}')) {
            $paragraphs[] = $this->docxParagraph('Chữ ký đại diện SportGo:', true);
            $paragraphs[] = $this->docxParagraph('{{signature_sportgo}}');
        }
        if (! str_contains($xml, '{{signature_owner}}')) {
            $paragraphs[] = $this->docxParagraph('Chữ ký đối tác/chủ sân:', true);
            $paragraphs[] = $this->docxParagraph('{{signature_owner}}');
        }

        $insert = implode('', $paragraphs);
        $xml = str_replace('</w:body>', $insert . '</w:body>', $xml);
        $zip->addFromString($entry, $xml);
    }

    private function appendWorkflowDocumentAppendix(ZipArchive $zip, array $data, string $documentType): void
    {
        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false || ! str_contains($xml, '</w:body>')) {
            return;
        }

        $titles = [
            'termination_request' => 'PHỤ LỤC THÔNG TIN YÊU CẦU CHẤM DỨT ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO',
            'mutual_liquidation_minutes' => 'PHỤ LỤC THÔNG TIN BIÊN BẢN THANH LÝ ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO',
            'unilateral_termination_notice' => 'PHỤ LỤC THÔNG TIN CÔNG VĂN CHẤM DỨT ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO',
            'settlement_minutes' => 'PHỤ LỤC THÔNG TIN BIÊN BẢN QUYẾT TOÁN ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO',
        ];

        $preferredKeys = match ($documentType) {
            'termination_request' => [
                'termination_code',
                'contract_code',
                'venue_name',
                'owner_full_name',
                'full_name',
                'termination_reason',
                'requested_at',
                'requested_by',
                'termination_type',
                'requested_effective_date',
                'owner_bank_account_snapshot',
            ],
            'mutual_liquidation_minutes' => [
                'liquidation_minutes_code',
                'contract_code',
                'termination_request_code',
                'venue_name',
                'party_a_rep',
                'party_b_name',
                'termination_reason',
                'agreed_termination_date',
                'effective_date',
                'settlement_table',
                'owner_wallet_available_amount',
                'unpaid_platform_fee_amount',
                'final_payable_to_owner',
                'final_receivable_from_owner',
                'owner_access_revocation_date',
            ],
            'unilateral_termination_notice' => [
                'document_number',
                'notice_code',
                'issue_date',
                'issuer_side',
                'receiver_name',
                'venue_owner_name',
                'contract_code',
                'venue_name',
                'legal_basis_text',
                'termination_reason',
                'effective_termination_date',
                'transition_end_at',
                'required_actions',
                'settlement_deadline',
                'issuer_representative_name',
            ],
            'settlement_minutes' => [
                'settlement_code',
                'settlement_date',
                'contract_code',
                'termination_request_code',
                'owner_full_name',
                'venue_name',
                'total_paid',
                'months_used',
                'months_remaining',
                'refund_amount',
                'owner_wallet_available_amount',
                'platform_fee_remaining_refund_amount',
                'unpaid_platform_fee_amount',
                'penalty_amount',
                'adjustment_amount',
                'final_payable_to_owner',
                'final_receivable_from_owner',
                'bank_account',
                'bank_name',
                'account_number',
                'account_holder_name',
                'settlement_items',
                'withdrawal_code',
                'withdrawal_status',
            ],
            default => array_keys($data),
        };

        $paragraphs = [
            $this->docxParagraph(''),
            $this->docxParagraph($titles[$documentType] ?? 'PHỤ LỤC THÔNG TIN VĂN BẢN ĐÃ ĐIỀN TRÊN HỆ THỐNG SPORTGO', true),
            $this->docxParagraph('Phần này được hệ thống tự động điền từ dữ liệu nghiệp vụ đã được lưu trên SportGo để bảo đảm file Word lưu, tải và preview có dữ liệu thật.'),
        ];

        foreach ($preferredKeys as $key) {
            $value = $data[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $paragraphs[] = $this->docxParagraph($this->humanLabel($key) . ': ' . $this->plainValue($value));
        }

        $paragraphs[] = $this->docxParagraph('');
        $paragraphs[] = $this->docxParagraph('Chữ ký đại diện SportGo:', true);
        $paragraphs[] = $this->docxParagraph('{{signature_sportgo}}');
        $paragraphs[] = $this->docxParagraph('Chữ ký/xác nhận đối tác/chủ sân:', true);
        $paragraphs[] = $this->docxParagraph('{{signature_owner}}');

        $insert = implode('', $paragraphs);
        $xml = str_replace('</w:body>', $insert . '</w:body>', $xml);
        $zip->addFromString($entry, $xml);
    }

    private function humanLabel(string $key): string
    {
        return Str::headline(str_replace('_', ' ', $key));
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
