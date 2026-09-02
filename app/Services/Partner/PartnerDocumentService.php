<?php

namespace App\Services\Partner;

use App\Models\GeneratedDocument;
use App\Models\GeneratedDocumentSignature;
use App\Models\DocumentTemplate;
use App\Models\Media;
use App\Models\PartnerApplication;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VenueCourt;
use App\Models\VenueCourtApprovalRequest;
use App\Models\VenueLocationChangeRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use Throwable;
use ZipArchive;

class PartnerDocumentService
{
    private const LAYOUT_PROFILE_VERSION = 2;

    private const TEMPLATE_FALLBACKS = [
        'partner_application_form' => 'Mau_01_Don_de_nghi_dang_ky_doi_tac_chu_san_SportGo_DA_SUA.docx',
        'partner_contract' => 'Mau_02_Hop_dong_hop_tac_doi_tac_SportGo_DA_SUA.docx',
        'termination_request' => 'Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo_DA_SUA.docx',
        'termination_cancellation_request' => 'Mau_03_Don_yeu_cau_cham_dut_hop_tac_SportGo_DA_SUA.docx',
        'mutual_liquidation_minutes' => 'Mau_04_Bien_ban_thanh_ly_hop_dong_hai_ben_SportGo_DA_SUA.docx',
        'unilateral_termination_notice' => 'Mau_05_Cong_van_cham_dut_hop_dong_don_phuong_SportGo_DA_SUA.docx',
        'settlement_minutes' => 'Mau_06_Bien_ban_quyet_toan_cham_dut_hop_tac_SportGo_DA_SUA.docx',
        'venue_scale_request' => 'Don_yeu_cau_thay_doi_quy_mo_san_SportGo.docx',
        'venue_location_change_request' => 'Don_yeu_cau_thay_doi_vi_tri_cum_san_SportGo.docx',
        'venue_scale_appendix' => 'Phu_luc_thay_doi_quy_mo_san_SportGo.docx',
        'venue_location_appendix' => 'Phu_luc_thay_doi_vi_tri_cum_san_SportGo.docx',
    ];

    private const DOCUMENT_PREFIXES = [
        'partner_application_form' => 'DKDT',
        'partner_contract' => 'HDHT',
        'termination_request' => 'DYCCD',
        'termination_cancellation_request' => 'HUYCCD',
        'mutual_liquidation_minutes' => 'BBTL',
        'unilateral_termination_notice' => 'CVCD',
        'settlement_minutes' => 'BBQT',
        'venue_scale_request' => 'YCQM',
        'venue_location_change_request' => 'YCVT',
        'venue_scale_appendix' => 'PLQM',
        'venue_location_appendix' => 'PLVT',
    ];

    public function __construct(
        private readonly DocumentPdfService $pdfs,
        private readonly PartnerDocumentFormatter $formatter
    ) {
    }

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
        $renderData = array_replace($renderData, SystemSetting::documentProfilePayload());
        $renderData = $this->withAutomaticAppendixIdentity(
            $documentType,
            $renderData,
            $context,
            $referenceType,
            $referenceId
        );
        $renderData = array_merge($renderData, [
            'document_code' => $documentCode,
            'document_version' => $documentVersion,
            'layout_profile_version' => self::LAYOUT_PROFILE_VERSION,
            'rendered_at' => $renderedAt->format('d/m/Y H:i'),
            'rendered_by' => $actor?->full_name ?? $actor?->username ?? $actor?->email ?? 'Hệ thống',
        ]);
        $filePath = 'generated-documents/' . $renderedAt->format('Y/m') . '/' . $documentCode . '.docx';
        $title = $context['title'] ?? $this->defaultTitle($documentType, $renderData);
        if (in_array($documentType, ['venue_scale_appendix', 'venue_location_appendix'], true)) {
            $title = $this->appendixDocumentTitle($title, (string) $renderData['appendix_number']);
        }

        $sourcePath = $template ? Storage::disk($template->storage_disk)->path($template->file_path) : null;
        if (! $sourcePath || ! $this->isUsableDocx($sourcePath)) {
            $sourcePath = $this->fallbackTemplatePath($documentType);
        }

        if (! $sourcePath || ! $this->isUsableDocx($sourcePath)) {
            throw new RuntimeException("Không tìm thấy template DOCX cho {$documentType}.");
        }

        $targetPath = Storage::disk('local')->path($filePath);
        $this->ensureLocalDirectory($targetPath);
        if ($documentType === 'termination_cancellation_request') {
            $this->renderTerminationCancellationDocument($targetPath, $renderData);
        } else {
            $this->renderDocxTemplateWithRetry($sourcePath, $targetPath, $renderData, $documentType);
        }
        $this->normalizeRequiredDocxParts($targetPath);
        $this->polishUnsignedSignaturePlaceholders($targetPath, $documentType);
        $this->formatter->normalize($targetPath, $documentType);
        $this->normalizeRequiredDocxParts($targetPath);
        if (! $this->isUsableDocx($targetPath)) {
            throw new RuntimeException("Không thể sinh file DOCX hợp lệ cho {$documentType}.");
        }

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

        // Keep DOCX private for rendering/signature operations. The partner
        // surface receives a controlled PDF copy with an audit watermark.
        $this->ensurePdfCopies($document->fresh());

        return $document->fresh();
    }

    public function signDocument(
        GeneratedDocument $document,
        User $signer,
        string $signerSide,
        ?string $signatureImage,
        Request $request,
        array $context = []
    ): GeneratedDocumentSignature {
        $signature = GeneratedDocumentSignature::query()->create([
            'generated_document_id' => $document->id,
            'signer_side' => $signerSide,
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
        ]);

        if ($signatureImage) {
            $media = $this->storeSignatureImage($signature, $signatureImage);
            $signature->forceFill(['signature_media_id' => $media->id])->save();
            
            // Inject signature into DOCX
            try {
                $filePath = Storage::disk('local')->path($document->generated_file_path);
                if (! file_exists($filePath)) {
                    throw new RuntimeException('Không tìm thấy file DOCX cần ký.');
                }

                $this->fixSplitMacrosInDocx($filePath);
                $this->replaceSignedTextPlaceholders($filePath, $signature, $signerSide);
                $this->restoreSignedSignatureTextStyle($filePath, $signature);
                $this->injectSignatureFallback($document->fresh(), $signature->fresh(), $signerSide, $media);
                $this->polishSignedDocumentFile($document->fresh());
                $this->restoreSignedSignatureTextStyle($filePath, $signature);
                $this->ensureSignerNameVisible($document->fresh(), $signature->fresh(), $signerSide);
                $this->normalizeRequiredDocxParts($filePath);

                if ($this->containsSignaturePlaceholder($filePath, $signerSide)
                    || ! $this->hasEmbeddedSignature($filePath, $signerSide, (int) $signature->id)) {
                    throw new RuntimeException('Không thể nhúng ảnh chữ ký vào file DOCX.');
                }

                $document->forceFill([
                    'file_hash' => hash_file('sha256', $filePath),
                ])->save();
            } catch (\Throwable $e) {
                Log::error('Failed to inject partner document signature image.', [
                    'document_id' => $document->id,
                    'signer_side' => $signerSide,
                    'error' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'signature_image' => 'Không thể chèn chữ ký vào văn bản. Hồ sơ chưa được đánh dấu hoàn tất.',
                ]);
            }
        }

        if (! $signatureImage) {
            $this->polishSignedDocumentFile($document->fresh());
            if ($document->generated_file_path) {
                $filePath = Storage::disk('local')->path($document->generated_file_path);
                if (file_exists($filePath)) {
                    $this->normalizeRequiredDocxParts($filePath);
                    $this->ensureSignerNameVisible($document->fresh(), $signature->fresh(), $signerSide);
                    $document->forceFill([
                        'file_hash' => hash_file('sha256', $filePath),
                    ])->save();
                }
            }
        }

        $isCompleted = $this->checkAllSigned($document->refresh());
        $document->forceFill([
            'status' => $isCompleted ? 'completed' : $this->nextStatusAfterSignature($document, $signerSide),
            'final_file_path' => $isCompleted ? ($document->final_file_path ?: $document->generated_file_path) : $document->final_file_path,
            'locked_at' => $document->locked_at ?: now(),
            'completed_at' => $isCompleted ? now() : $document->completed_at,
        ])->save();

        $this->syncVenueChangeAppendixAfterSignature($document->refresh(), $signerSide, $isCompleted);

        $this->ensurePdfCopies($document->refresh());

        return $signature;
    }

    public function checkAllSigned(GeneratedDocument $document): bool
    {
        $signedSides = $document->signatures()
            ->where('status', 'signed')
            ->pluck('signer_side')
            ->all();

        if (in_array($document->document_type, ['partner_application_form', 'venue_scale_request', 'venue_location_change_request', 'termination_request', 'owner_termination_request', 'termination_cancellation_request'], true)) {
            return in_array('owner', $signedSides, true);
        }

        if ($document->document_type === 'unilateral_termination_notice') {
            return in_array('sportgo', $signedSides, true);
        }

        if (in_array($document->document_type, ['venue_scale_appendix', 'venue_location_appendix'], true)) {
            return in_array('owner', $signedSides, true) && in_array('sportgo', $signedSides, true);
        }

        return in_array('owner', $signedSides, true) && in_array('sportgo', $signedSides, true);
    }

    private function nextStatusAfterSignature(GeneratedDocument $document, string $signerSide): string
    {
        if (in_array($document->document_type, ['partner_contract', 'venue_scale_appendix', 'venue_location_appendix'], true)) {
            return $signerSide === 'sportgo' ? 'pending_owner_signature' : 'pending_sportgo_signature';
        }

        if ($document->document_type === 'partner_application_form' && $signerSide === 'owner') {
            return 'completed';
        }

        if (in_array($document->document_type, ['venue_scale_request', 'venue_location_change_request'], true) && $signerSide === 'owner') {
            return 'completed';
        }

        if (in_array($document->document_type, ['termination_request', 'owner_termination_request', 'termination_cancellation_request'], true) && $signerSide === 'owner') {
            return 'completed';
        }

        if ($document->document_type === 'unilateral_termination_notice' && $signerSide === 'sportgo') {
            return 'completed';
        }

        return $document->status;
    }

    private function syncVenueChangeAppendixAfterSignature(GeneratedDocument $document, string $signerSide, bool $isCompleted): void
    {
        if (! in_array($document->document_type, ['venue_scale_appendix', 'venue_location_appendix'], true)) {
            return;
        }

        if ($signerSide === 'sportgo' && ! $isCompleted) {
            $this->markVenueChangeRequestStatus($document, 'pending_owner_signature');
            return;
        }

        if (! $isCompleted) {
            return;
        }

        if ($document->document_type === 'venue_scale_appendix') {
            $request = VenueCourtApprovalRequest::query()
                ->whereKey($document->reference_id)
                ->with(['venueCluster', 'courtType'])
                ->first();

            if (! $request || $request->status === 'completed') {
                return;
            }

            $createdCourtId = $request->approved_venue_court_id;
            $requestedCourts = collect($request->requested_courts ?: []);

            if ($requestedCourts->isEmpty() && ! in_array($request->change_type, ['remove'], true)) {
                $requestedCourts = collect([[
                    'court_type_id' => $request->court_type_id,
                    'name' => $request->name,
                ]]);
            }

            if (! in_array($request->change_type, ['remove'], true)) {
                $nextSortOrder = (int) VenueCourt::query()
                    ->where('venue_cluster_id', $request->venue_cluster_id)
                    ->max('sort_order');

                foreach ($requestedCourts as $courtData) {
                    $courtTypeId = (int) ($courtData['court_type_id'] ?? 0);
                    $courtName = trim((string) ($courtData['name'] ?? ''));

                    if ($courtTypeId <= 0 || $courtName === '') {
                        continue;
                    }

                    $court = VenueCourt::create([
                        'venue_cluster_id' => $request->venue_cluster_id,
                        'court_type_id' => $courtTypeId,
                        'name' => $courtName,
                        'status' => 'active',
                        'sort_order' => ++$nextSortOrder,
                    ]);

                    $createdCourtId ??= $court->id;
                }
            }

            $removedCourtIds = collect($request->removed_court_ids ?: [])
                ->filter()
                ->values()
                ->all();

            if ($removedCourtIds !== []) {
                // Soft delete keeps signed-contract history while hiding removed courts from booking/configuration.
                VenueCourt::query()
                    ->where('venue_cluster_id', $request->venue_cluster_id)
                    ->whereIn('id', $removedCourtIds)
                    ->delete();
            }

            if ($createdCourtId && ! $request->approved_venue_court_id) {
                $request->forceFill(['approved_venue_court_id' => $createdCourtId])->save();
            }

            $request->forceFill([
                'status' => 'completed',
                'status_reason' => null,
            ])->save();

            return;
        }

        $request = VenueLocationChangeRequest::query()
            ->whereKey($document->reference_id)
            ->with('venueCluster')
            ->first();

        if (! $request || $request->status === 'completed' || ! $request->venueCluster) {
            return;
        }

        $request->venueCluster->forceFill([
            'address' => $request->new_address,
            'province' => $request->new_province,
            'province_code' => $request->new_province_code,
            'ward' => $request->new_ward,
            'ward_code' => $request->new_ward_code,
            'latitude' => $request->new_latitude,
            'longitude' => $request->new_longitude,
            'map_url' => $request->new_map_url,
        ])->save();

        $request->forceFill([
            'status' => 'completed',
            'status_reason' => null,
        ])->save();
    }

    private function markVenueChangeRequestStatus(GeneratedDocument $document, string $status): void
    {
        if ($document->document_type === 'venue_scale_appendix') {
            VenueCourtApprovalRequest::query()
                ->whereKey($document->reference_id)
                ->where('status', 'approved_pending_appendix')
                ->update(['status' => $status]);
        }

        if ($document->document_type === 'venue_location_appendix') {
            VenueLocationChangeRequest::query()
                ->whereKey($document->reference_id)
                ->where('status', 'approved_pending_appendix')
                ->update(['status' => $status]);
        }
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

        if (! $document->locked_at) {
            $this->normalizeRequiredDocxParts(Storage::disk('local')->path($path));
            $this->repairSignedDocumentPlaceholders($document);
            $this->repairVisibleSignerNames($document);
        }

        return $path;
    }

    /**
     * Resolve the PDF delivered to partner/admin clients. Existing DOCX
     * records are converted lazily so deployments do not need a bulk job.
     */
    public function pdfDownloadPath(GeneratedDocument $document): string
    {
        $document->refresh();
        $this->refreshLegacyUnsignedDocumentLayout($document);
        $document->refresh();
        $this->ensurePdfCopies($document);

        $path = $document->final_pdf_path ?: $document->generated_pdf_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Không tìm thấy bản PDF của văn bản.');
        }

        return $path;
    }

    private function refreshLegacyUnsignedDocumentLayout(GeneratedDocument $document): void
    {
        if ((int) ($document->render_data['layout_profile_version'] ?? 0) >= self::LAYOUT_PROFILE_VERSION
            || $document->locked_at
            || $document->final_file_path
            || in_array($document->status, ['signed', 'completed', 'cancelled', 'voided'], true)
            || $document->signatures()->where('status', 'signed')->exists()) {
            return;
        }

        $relativePath = $document->generated_file_path;
        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            return;
        }

        $template = $this->activeTemplate($document->document_type);
        $sourcePath = $template ? Storage::disk($template->storage_disk)->path($template->file_path) : null;
        if (! $sourcePath || ! $this->isUsableDocx($sourcePath)) {
            $sourcePath = $this->fallbackTemplatePath($document->document_type);
        }
        if (! $sourcePath || ! $this->isUsableDocx($sourcePath)) {
            return;
        }

        $renderData = array_replace($document->render_data ?: [], SystemSetting::documentProfilePayload());
        $renderData = $this->hydrateVenueChangeEvidence($document, $renderData);
        $renderData = $this->withAutomaticAppendixIdentity(
            $document->document_type,
            $renderData,
            [
                'partner_contract_id' => $document->partner_contract_id,
                'venue_cluster_id' => $document->venue_cluster_id,
                'entity_type' => $document->entity_type,
                'entity_id' => $document->entity_id,
            ],
            (string) $document->reference_type,
            (string) $document->reference_id
        );
        $renderData = array_merge($renderData, [
            'document_code' => $document->document_code,
            'document_version' => (int) ($document->document_version ?: 1),
            'layout_profile_version' => self::LAYOUT_PROFILE_VERSION,
            'rendered_at' => ($document->generated_at ?: now())->format('d/m/Y H:i'),
            'rendered_by' => $renderData['rendered_by'] ?? 'Hệ thống',
        ]);

        $targetPath = Storage::disk('local')->path($relativePath);
        $temporaryPath = $targetPath.'.layout-v'.self::LAYOUT_PROFILE_VERSION.'-'.bin2hex(random_bytes(6)).'.docx';

        try {
            $this->renderDocxTemplateWithRetry($sourcePath, $temporaryPath, $renderData, $document->document_type);
            $this->normalizeRequiredDocxParts($temporaryPath);
            $this->polishUnsignedSignaturePlaceholders($temporaryPath, $document->document_type);
            $this->formatter->normalize($temporaryPath, $document->document_type);
            $this->normalizeRequiredDocxParts($temporaryPath);

            if (! $this->isUsableDocx($temporaryPath) || ! copy($temporaryPath, $targetPath)) {
                throw new RuntimeException('Không thể cập nhật định dạng tài liệu chưa ký.');
            }

            if ($document->generated_pdf_path && Storage::disk('local')->exists($document->generated_pdf_path)) {
                Storage::disk('local')->delete($document->generated_pdf_path);
            }

            $document->forceFill([
                'template_id' => $template?->id,
                'template_version' => $template?->version ?? $document->template_version,
                'render_data' => $renderData,
                'file_hash' => hash_file('sha256', $targetPath),
                'pdf_hash' => null,
                'pdf_generated_at' => null,
                'pdf_locked_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Không thể tự động cập nhật định dạng tài liệu đối tác chưa ký.', [
                'generated_document_id' => $document->id,
                'document_type' => $document->document_type,
                'error' => $exception->getMessage(),
            ]);
        } finally {
            @unlink($temporaryPath);
            @unlink($temporaryPath.'.tmp.docx');
        }
    }

    private function hydrateVenueChangeEvidence(GeneratedDocument $document, array $renderData): array
    {
        if (in_array($document->document_type, ['venue_scale_request', 'venue_scale_appendix'], true)
            && $document->reference_type === VenueCourtApprovalRequest::class) {
            $request = VenueCourtApprovalRequest::query()->find($document->reference_id);
            if ($request) {
                $renderData['reason'] = $request->status_reason ?: ($renderData['reason'] ?? null);
                $renderData['attachment_list'] = $this->storedVenueRequestDocumentNames($request->supplementary_documents);
                $renderData['evidence_present'] = filled($request->evidence_image);
            }
        }

        if (in_array($document->document_type, ['venue_location_change_request', 'venue_location_appendix'], true)
            && $document->reference_type === VenueLocationChangeRequest::class) {
            $request = VenueLocationChangeRequest::query()->find($document->reference_id);
            if ($request) {
                $renderData['reason'] = $request->note ?: $request->status_reason ?: ($renderData['reason'] ?? null);
                $renderData['attachment_list'] = $this->storedVenueRequestDocumentNames($request->supplementary_documents);
            }
        }

        return $renderData;
    }

    private function storedVenueRequestDocumentNames(mixed $documents): string
    {
        return collect($documents ?: [])
            ->map(fn ($document) => is_array($document)
                ? ($document['file_name'] ?? $document['original_name'] ?? $document['name'] ?? null)
                : null)
            ->filter()
            ->values()
            ->implode('; ');
    }

    public function pdfDownloadName(GeneratedDocument $document): string
    {
        $base = match ($document->document_type) {
            'partner_contract' => 'HopDong',
            'mutual_liquidation_minutes', 'settlement_minutes' => 'BienBan',
            'unilateral_termination_notice' => 'CongVan',
            default => 'VanBan',
        };

        return $base . '_' . $document->document_code . '.pdf';
    }

    public function ensurePdfCopies(GeneratedDocument $document): void
    {
        $sourcePath = $document->final_file_path ?: $document->generated_file_path;
        if (! $sourcePath || ! Storage::disk('local')->exists($sourcePath)) {
            return;
        }

        $sourceAbsolutePath = Storage::disk('local')->path($sourcePath);
        $relativePdfPath = $document->generated_pdf_path
            ?: 'generated-documents/' . ($document->generated_at?->format('Y/m') ?: now()->format('Y/m')) . '/' . $document->document_code . '.pdf';
        $pdfAbsolutePath = Storage::disk('local')->path($relativePdfPath);
        $sourceModifiedAt = @filemtime($sourceAbsolutePath);
        $pdfGeneratedAt = $document->pdf_generated_at?->getTimestamp();
        $watermark = sprintf(
            'BẢN SAO KIỂM SOÁT | %s | Phiên bản %d | Trạng thái: %s | Phát hành: %s',
            $document->document_code,
            (int) ($document->document_version ?: 1),
            $this->documentStatusLabel($document->status),
            (string) ($document->render_data['rendered_by'] ?? 'Hệ thống')
        );

        // An unsigned document does not have pdf_locked_at yet, but that does
        // not mean its PDF is invalid. Re-rendering on every preview request
        // made the endpoint depend on the DOCX renderer for every page load
        // and caused intermittent 500 responses on production. Re-render only
        // when the source DOCX is newer than the cached PDF, or when the PDF
        // is missing/invalid. The layout and signature flows also invalidate
        // the cached metadata when they replace the source document.
        $needsRegeneration = ! Storage::disk('local')->exists($relativePdfPath)
            || ! $document->pdf_hash
            || ! hash_equals((string) $document->pdf_hash, hash_file('sha256', $pdfAbsolutePath))
            || ($pdfGeneratedAt !== null && $sourceModifiedAt !== false && $sourceModifiedAt > $pdfGeneratedAt);

        if ($needsRegeneration) {
            $this->pdfs->convertDocx($sourceAbsolutePath, $pdfAbsolutePath, $watermark);
            $document->forceFill([
                'generated_pdf_path' => $relativePdfPath,
                'pdf_hash' => hash_file('sha256', $pdfAbsolutePath),
                'pdf_generated_at' => now(),
            ])->save();
        }

        if ($document->status === 'completed') {
            $finalPdfPath = $document->final_pdf_path
                ?: 'generated-documents/' . ($document->completed_at?->format('Y/m') ?: now()->format('Y/m')) . '/' . $document->document_code . '-final.pdf';
            $finalPdfAbsolutePath = Storage::disk('local')->path($finalPdfPath);
            $needsFinalCopy = ! Storage::disk('local')->exists($finalPdfPath)
                || ! $document->final_pdf_hash
                || ! hash_equals((string) $document->final_pdf_hash, hash_file('sha256', $finalPdfAbsolutePath));

            if ($needsFinalCopy) {
                if (! copy($pdfAbsolutePath, $finalPdfAbsolutePath)) {
                    throw new RuntimeException('Không thể khóa bản PDF hoàn tất của văn bản.');
                }
                $document->forceFill([
                    'final_pdf_path' => $finalPdfPath,
                    'final_pdf_hash' => hash_file('sha256', $finalPdfAbsolutePath),
                    'pdf_locked_at' => $document->pdf_locked_at ?: now(),
                ])->save();
            }
        }
    }

    private function documentStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending_owner_signature' => 'chờ chủ sân ký',
            'pending_sportgo_signature' => 'chờ SportGo ký',
            'completed' => 'hoàn tất',
            'cancelled' => 'đã hủy',
            default => 'bản nháp',
        };
    }

    private function repairVisibleSignerNames(GeneratedDocument $document): void
    {
        $document->loadMissing('signatures');

        foreach ($document->signatures->where('status', 'signed') as $signature) {
            $this->ensureSignerNameVisible($document, $signature, (string) $signature->signer_side);
        }

        $path = $document->generated_file_path;
        if ($path && Storage::disk('local')->exists($path)) {
            $document->forceFill([
                'file_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
            ])->save();
        }
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

    private function isUsableDocx(?string $path): bool
    {
        if (! $path || ! is_file($path) || filesize($path) < 1024 || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }

        $usable = $zip->getFromName('word/document.xml') !== false;
        $zip->close();

        return $usable;
    }

    private function ensureLocalDirectory(string $targetPath): void
    {
        $directory = dirname($targetPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    /** @param array<string, mixed> $data */
    private function renderTerminationCancellationDocument(string $targetPath, array $data): void
    {
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(13);
        $section = $word->addSection([
            'marginTop' => 1134,
            'marginRight' => 1134,
            'marginBottom' => 1134,
            'marginLeft' => 1701,
        ]);
        $center = ['alignment' => 'center', 'spaceAfter' => 0];
        $section->addText('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', ['bold' => true], $center);
        $section->addText('Độc lập - Tự do - Hạnh phúc', ['bold' => true], $center);
        $section->addText('----------------', [], $center);
        $section->addTextBreak();
        $section->addText(
            sprintf(
                '%s, ngày %s tháng %s năm %s',
                $data['document_place'] ?? 'Hà Nội',
                $data['document_day'] ?? now()->format('d'),
                $data['document_month'] ?? now()->format('m'),
                $data['document_year'] ?? now()->format('Y')
            ),
            [],
            ['alignment' => 'right']
        );
        $section->addText('ĐƠN XÁC NHẬN HỦY YÊU CẦU CHẤM DỨT HỢP TÁC', ['bold' => true, 'size' => 15], $center);
        $section->addText(
            'Kính gửi: '.($data['sportgo_company_name'] ?? 'Công ty TNHH SportGo'),
            ['bold' => true],
            ['spaceBefore' => 180, 'spaceAfter' => 180]
        );
        $section->addText(
            'Tôi là chủ sân/đối tác đã gửi yêu cầu chấm dứt hợp tác, nay xác nhận hủy yêu cầu đó với các thông tin dưới đây:',
            [],
            ['alignment' => 'both', 'spaceAfter' => 160]
        );

        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 100,
            'width' => 9000,
            'unit' => 'dxa',
        ]);
        $rows = [
            ['Chủ sân/đối tác', $data['owner_name'] ?? 'Chưa cung cấp'],
            ['Email / Số điện thoại', trim(($data['owner_email'] ?? '') . ' / ' . ($data['owner_phone'] ?? ''), ' /') ?: 'Chưa cung cấp'],
            ['Cụm sân', $data['venue_name'] ?? 'Chưa cung cấp'],
            ['Hợp đồng', $data['contract_code'] ?? 'Chưa cung cấp'],
            ['Mã hồ sơ chấm dứt', $data['termination_code'] ?? 'Chưa cung cấp'],
            ['Đơn yêu cầu gốc', $data['original_document_code'] ?? 'Chưa cung cấp'],
            ['Lý do hủy yêu cầu', $data['cancellation_reason'] ?? 'Chưa cung cấp'],
            ['Thời điểm xác nhận', $data['cancellation_requested_at'] ?? now()->format('d/m/Y H:i')],
        ];
        foreach ($rows as [$label, $value]) {
            $table->addRow();
            $table->addCell(2700)->addText((string) $label, ['bold' => true]);
            $table->addCell(6300)->addText((string) $value, [], ['alignment' => 'left']);
        }

        $section->addText('XÁC NHẬN VÀ CAM KẾT', ['bold' => true], ['spaceBefore' => 220, 'spaceAfter' => 80]);
        foreach ([
            'Tôi xác nhận tự nguyện hủy yêu cầu chấm dứt hợp tác nêu trên.',
            'Cụm sân chỉ được mở lại hoạt động bình thường khi hệ thống xác nhận không có xử lý không thể đảo ngược.',
            'Đơn gốc, chữ ký, lịch sử xử lý và chứng từ đã phát sinh tiếp tục được lưu để đối soát; dữ liệu không bị xóa.',
            'Các booking, hoàn tiền, rút tiền hoặc thao tác thủ công đã hoàn tất không tự động rollback.',
        ] as $commitment) {
            $section->addText('• ' . $commitment, [], ['alignment' => 'both', 'spaceAfter' => 60]);
        }

        $signatureTable = $section->addTable(['borderSize' => 0, 'width' => 9000, 'unit' => 'dxa']);
        $signatureTable->addRow();
        $signatureTable->addCell(4500)->addText('');
        $signatureCell = $signatureTable->addCell(4500);
        $signatureCell->addText('CHỦ SÂN/ĐỐI TÁC', ['bold' => true], $center);
        $signatureCell->addText('(Ký và ghi rõ họ tên)', ['italic' => true], $center);
        $signatureCell->addText('{{signature_owner}}', [], $center);
        $signatureCell->addText('{{owner_signer_name}}', ['bold' => true], $center);

        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($targetPath);
    }

    private function renderDocxTemplate(string $sourcePath, string $targetPath, array $data, string $documentType): void
    {
        $tempPath = $targetPath . '.tmp.docx';
        copy($sourcePath, $tempPath);
        $this->normalizeRequiredDocxParts($tempPath);
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
        $this->replaceResidualTemplateBlanks($targetPath, $documentType);
        $this->polishUnsignedSignaturePlaceholders($targetPath, $documentType);
        $this->normalizeRequiredDocxParts($targetPath);
    }

    private function renderDocxTemplateWithRetry(string $sourcePath, string $targetPath, array $data, string $documentType): void
    {
        $attempts = 3;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->renderDocxTemplate($sourcePath, $targetPath, $data, $documentType);

                return;
            } catch (Throwable $exception) {
                $isTransientWindowsLock = str_contains($exception->getMessage(), 'Renaming temporary file failed')
                    || str_contains($exception->getMessage(), 'Permission denied');

                if (! $isTransientWindowsLock || $attempt === $attempts) {
                    throw $exception;
                }

                clearstatcache(true, $targetPath);
                @unlink($targetPath);
                @unlink($targetPath.'.tmp.docx');
                usleep(150000 * $attempt);
            }
        }
    }

    private function normalizeRequiredDocxParts(string $docxPath): void
    {
        if (! is_file($docxPath) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $styles = $zip->getFromName('word/styles.xml');
        if ($styles === false || trim($styles) === '') {
            $fallbackStyles = $zip->getFromName('word/stylesWithEffects.xml');
            $zip->addFromString(
                'word/styles.xml',
                is_string($fallbackStyles) && trim($fallbackStyles) !== ''
                    ? $fallbackStyles
                    : $this->minimalStylesXml()
            );
        }

        $settings = $zip->getFromName('word/settings.xml');
        if ($settings === false || trim($settings) === '') {
            $zip->addFromString('word/settings.xml', $this->minimalSettingsXml());
        }

        $zip->close();
    }

    private function minimalStylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr>
    </w:rPrDefault>
    <w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>
</w:styles>
XML;
    }

    private function minimalSettingsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>
XML;
    }

    private function repairSignedDocumentPlaceholders(GeneratedDocument $document): void
    {
        if (! $document->generated_file_path || ! Storage::disk('local')->exists($document->generated_file_path)) {
            return;
        }

        $document->loadMissing('signatures');
        foreach ($document->signatures->where('status', 'signed') as $signature) {
            if (! $signature->signature_media_id) {
                continue;
            }

            $media = Media::query()->find($signature->signature_media_id);
            if (! $media) {
                continue;
            }

            $this->injectSignatureFallback($document, $signature, $signature->signer_side, $media);
        }

        $filePath = Storage::disk('local')->path($document->generated_file_path);
        if (is_file($filePath)) {
            $document->forceFill(['file_hash' => hash_file('sha256', $filePath)])->save();
        }
    }

    private function injectSignatureFallback(GeneratedDocument $document, GeneratedDocumentSignature $signature, string $signerSide, Media $media): void
    {
        $path = $document->generated_file_path;
        if (! $path || ! Storage::disk('local')->exists($path) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $imagePath = $this->publicMediaPath($media);
        if (! $imagePath || ! is_file($imagePath)) {
            return;
        }

        $filePath = Storage::disk('local')->path($path);
        $this->fixSplitMacrosInDocx($filePath);

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            return;
        }

        $changed = false;
        $namePlaceholder = '{{' . $signerSide . '_signer_name}}';
        $signaturePlaceholder = '{{signature_' . $signerSide . '}}';

        if (str_contains($documentXml, $namePlaceholder)) {
            $documentXml = str_replace($namePlaceholder, $this->xmlText($signature->signer_full_name ?: ''), $documentXml);
            $changed = true;
        }

        $mediaFileName = 'signature-' . $signerSide . '-' . $signature->id . '.png';
        $mediaTarget = 'word/media/' . $mediaFileName;
        $zip->addFromString($mediaTarget, file_get_contents($imagePath));
        $this->ensurePngContentType($zip);

        $relationshipId = $this->ensureImageRelationship($zip, 'media/' . $mediaFileName);
        if ($relationshipId) {
            $embedMarker = 'r:embed="' . $relationshipId . '"';
            if (! str_contains($documentXml, $embedMarker)) {
                $drawingRun = $this->signatureDrawingRunXml($relationshipId, $mediaFileName, $imagePath);
                $drawingParagraph = '<w:p><w:pPr><w:jc w:val="center"/></w:pPr>' . $drawingRun . '</w:p>';
                $pattern = '~<w:r\b[^>]*>(?:(?!</w:r>).)*<w:t\b[^>]*>\{\{signature_' . preg_quote($signerSide, '~') . '\}\}</w:t>(?:(?!</w:r>).)*</w:r>~s';
                $documentXml = preg_replace($pattern, $drawingRun, $documentXml, 1, $count);
                if (! $count) {
                    $paragraphPattern = '~<w:p\b[^>]*>.*?' . preg_quote($signaturePlaceholder, '~') . '.*?</w:p>~s';
                    $documentXml = preg_replace($paragraphPattern, $drawingParagraph, $documentXml, 1, $paragraphCount);
                    if (! $paragraphCount) {
                        $signerName = $this->xmlText((string) $signature->signer_full_name);
                        $nameParagraphPattern = '~(<w:p\b[^>]*>.*?' . preg_quote($signerName, '~') . '.*?</w:p>)~s';
                        $documentXml = preg_replace($nameParagraphPattern, $drawingParagraph . '$1', $documentXml, 1, $nameParagraphCount);
                        if (! $nameParagraphCount) {
                            $documentXml = str_replace('<w:sectPr', $drawingParagraph . '<w:sectPr', $documentXml, $sectionCount);
                            if (! $sectionCount) {
                                $documentXml = str_replace('</w:body>', $drawingParagraph . '</w:body>', $documentXml);
                            }
                        }
                    }
                }
            }
            $changed = true;
        }

        if ($changed) {
            $zip->addFromString('word/document.xml', $documentXml);
        }

        $zip->close();
    }

    private function replaceSignedTextPlaceholders(string $docxPath, GeneratedDocumentSignature $signature, string $signerSide): void
    {
        if (! is_file($docxPath) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $this->fixSplitMacrosInDocx($docxPath);

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $changed = false;
        $values = [
            '{{' . $signerSide . '_signer_name}}' => $signature->signer_full_name ?: '',
            '{{' . $signerSide . '_signer_full_name}}' => $signature->signer_full_name ?: '',
        ];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! str_starts_with($entry, 'word/') || ! str_ends_with($entry, '.xml')) {
                continue;
            }

            $xml = $zip->getFromName($entry);
            if ($xml === false) {
                continue;
            }

            $nextXml = $xml;
            foreach ($values as $placeholder => $value) {
                $nextXml = str_replace($placeholder, $this->xmlText($value), $nextXml);
            }

            if ($nextXml !== $xml) {
                $zip->addFromString($entry, $nextXml);
                $changed = true;
            }
        }

        $zip->close();

        if ($changed) {
            $this->normalizeRequiredDocxParts($docxPath);
        }
    }

    private function ensureSignerNameVisible(GeneratedDocument $document, GeneratedDocumentSignature $signature, string $signerSide): void
    {
        $name = trim((string) $signature->signer_full_name);
        $path = $document->generated_file_path;

        if ($name === '' || ! $path || ! Storage::disk('local')->exists($path) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $filePath = Storage::disk('local')->path($path);
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return;
        }

        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false) {
            $zip->close();
            return;
        }

        if ($this->signerNameAlreadyNearSignature($xml, $name)) {
            $zip->close();
            return;
        }

        $nameParagraph = $this->signedNameParagraphXml($name);
        $patterns = [
            '~(<w:p\b[^>]*>.*?<w:drawing\b.*?</w:p>)~s',
            '~(<w:p\b[^>]*>.*?signature[_-]' . preg_quote($signerSide, '~') . '.*?</w:p>)~s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $xml, $matches, PREG_OFFSET_CAPTURE) && $matches[1]) {
                $last = end($matches[1]);
                $offset = $last[1] + strlen($last[0]);
                $xml = substr($xml, 0, $offset) . $nameParagraph . substr($xml, $offset);
                $zip->addFromString($entry, $xml);
                $zip->close();
                return;
            }
        }

        if (str_contains($xml, '</w:body>')) {
            $xml = str_replace('</w:body>', $nameParagraph . '</w:body>', $xml);
            $zip->addFromString($entry, $xml);
        }

        $zip->close();
    }

    private function signedNameParagraphXml(string $name): string
    {
        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">'
            . $this->xmlText($name)
            . '</w:t></w:r></w:p>';
    }

    private function signerNameAlreadyNearSignature(string $xml, string $name): bool
    {
        if (str_contains($xml, $this->xmlText($name))) {
            return true;
        }

        $escaped = preg_quote($this->xmlText($name), '~');

        return (bool) preg_match(
            '~<w:p\b[^>]*>.*?<w:drawing\b.*?</w:p>(?:(?!<w:p\b).)*<w:p\b[^>]*>.*?' . $escaped . '.*?</w:p>~s',
            $xml
        );
    }

    private function ensurePngContentType(ZipArchive $zip): void
    {
        $entry = '[Content_Types].xml';
        $xml = $zip->getFromName($entry);
        if ($xml === false || trim($xml) === '') {
            return;
        }

        if (str_contains($xml, 'Extension="png"')) {
            return;
        }

        $override = '<Default Extension="png" ContentType="image/png"/>';
        $xml = str_replace('</Types>', $override . '</Types>', $xml);
        $zip->addFromString($entry, $xml);
    }

    private function ensureImageRelationship(ZipArchive $zip, string $target): ?string
    {
        $entry = 'word/_rels/document.xml.rels';
        $relsXml = $zip->getFromName($entry);
        if ($relsXml === false || trim($relsXml) === '') {
            $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
        }

        if (preg_match('~<Relationship\s+[^>]*Id="([^"]+)"[^>]*Target="' . preg_quote($target, '~') . '"~', $relsXml, $match)) {
            return $match[1];
        }

        preg_match_all('~Id="rId(\d+)"~', $relsXml, $matches);
        $next = $matches[1] ? (max(array_map('intval', $matches[1])) + 1) : 1;
        $relationshipId = 'rId' . $next;
        $relationship = '<Relationship Id="' . $relationshipId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="' . $this->xmlAttr($target) . '"/>';
        $relsXml = str_replace('</Relationships>', $relationship . '</Relationships>', $relsXml);
        $zip->addFromString($entry, $relsXml);

        return $relationshipId;
    }

    private function signatureDrawingRunXml(string $relationshipId, string $name, string $imagePath): string
    {
        [$width, $height] = getimagesize($imagePath) ?: [360, 160];
        $widthEmu = 1524000;
        $heightEmu = max(300000, (int) round($widthEmu * max(1, $height) / max(1, $width)));
        $docPrId = random_int(10000, 999999);

        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            . '<wp:extent cx="' . $widthEmu . '" cy="' . $heightEmu . '"/>'
            . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
            . '<wp:docPr id="' . $docPrId . '" name="' . $this->xmlAttr($name) . '"/>'
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"/></wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="0" name="' . $this->xmlAttr($name) . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $this->xmlAttr($relationshipId) . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $widthEmu . '" cy="' . $heightEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
    }

    private function publicMediaPath(Media $media): ?string
    {
        $path = $media->getRawOriginal('file_path') ?: $media->file_path;
        $path = preg_replace('~^/storage/~', '', (string) $path);

        return $path ? Storage::disk('public')->path(ltrim($path, '/')) : null;
    }

    private function containsSignaturePlaceholder(string $docxPath, string $signerSide): bool
    {
        if (! is_file($docxPath) || ! class_exists(ZipArchive::class)) {
            return true;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return true;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml === false || str_contains($xml, '{{signature_' . $signerSide . '}}');
    }

    private function hasEmbeddedSignature(string $docxPath, string $signerSide, int $signatureId): bool
    {
        if (! is_file($docxPath) || ! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return false;
        }

        $mediaName = 'signature-' . $signerSide . '-' . $signatureId . '.png';
        $mediaEntry = 'word/media/' . $mediaName;
        $documentXml = $zip->getFromName('word/document.xml');
        $relationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $hasMedia = $zip->locateName($mediaEntry) !== false;
        $zip->close();

        if (! $hasMedia || $documentXml === false || $relationshipsXml === false) {
            return false;
        }

        $target = 'media/' . preg_quote($mediaName, '~');
        if (! preg_match('~<Relationship\s+[^>]*Id="([^"]+)"[^>]*Target="' . $target . '"~', $relationshipsXml, $match)) {
            return false;
        }

        return str_contains($documentXml, 'r:embed="' . $match[1] . '"');
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function xmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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

            if (is_string($replaced) && $replaced !== $xml) {
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

            if (is_string($replaced) && $replaced !== $xml) {
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
            $recipient = 'Kính gửi: '.($data['sportgo_company_name'] ?? 'Công ty TNHH SportGo');
            $replaced = preg_replace_callback(
                '/Kính gửi:\s*(?:<[^>]+>)*\[Tên công ty\/đơn vị vận hành nền tảng SportGo\]/u',
                fn (): string => $recipient,
                $replaced
            );
            $replaced = preg_replace_callback(
                '/Kính gửi:\s*(?:<[^>]+>)*\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\.\./u',
                fn (): string => $recipient,
                $replaced
            );

            // Auto-fill contract number and date if they use dots instead of placeholders
            if (isset($data['contract_code'])) {
                $replaced = preg_replace('/(Số:\s*(?:<[^>]+>)*)[\. \t]+((?:<[^>]+>)*\/HĐHT-SG)/u', '${1}' . $data['contract_code'] . '${2}', $replaced);
            }
            if (isset($data['location_date'])) {
                // Thay vì replace cả ngày tháng năm, chúng ta tìm và replace một cách an toàn
                $replaced = preg_replace('/[\. \t_]*(?:<[^>]+>)*,?\s*(?:<[^>]+>)*ngày\s*(?:<[^>]+>)*[\. \t_]+(?:<[^>]+>)*tháng\s*(?:<[^>]+>)*[\. \t_]+(?:<[^>]+>)*năm\s*(?:<[^>]+>)*[\. \t_]+/u', $data['location_date'], $replaced);
            }

            if (is_string($replaced) && $replaced !== $xml) {
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
            'termination_request',
            'termination_cancellation_request',
            'mutual_liquidation_minutes',
            'unilateral_termination_notice',
            'settlement_minutes' => $this->workflowTemplateBodyValues($data, $documentType),
            'venue_scale_request',
            'venue_location_change_request',
            'venue_scale_appendix',
            'venue_location_appendix' => $this->venueChangeRequestTemplateBodyValues($data, $documentType),
            default => [],
        };

        if ($fields === []) {
            return;
        }

        $this->fillTwoColumnTemplateBodyFields($docxPath, $fields, $documentType);
        $this->fillVenueChangeStructuredTables($docxPath, $data, $documentType);
        if (in_array($documentType, [
            'partner_application_form',
            'partner_contract',
            'termination_request',
            'mutual_liquidation_minutes',
            'unilateral_termination_notice',
            'settlement_minutes',
            'venue_scale_request',
            'venue_location_change_request',
            'venue_scale_appendix',
            'venue_location_appendix',
        ], true)) {
            $this->normalizeTwoColumnTableWidths($docxPath);
        }
        $this->fillKnownTemplateInlineText($docxPath, $data, $documentType);
        $this->replaceVenueChangeReasonSection($docxPath, $data, $documentType);
        $this->replaceVenueChangeAttachmentSection($docxPath, $data, $documentType);
        $this->ensureDocumentSignaturePlaceholders($docxPath, $documentType);
        $this->polishUnsignedSignaturePlaceholders($docxPath, $documentType);
        $this->replaceResidualTemplateBlanks($docxPath, $documentType);
    }

    private function fillVenueChangeStructuredTables(string $docxPath, array $data, string $documentType): void
    {
        if (! in_array($documentType, ['venue_scale_request', 'venue_location_change_request', 'venue_scale_appendix', 'venue_location_appendix'], true)
            || ! class_exists(ZipArchive::class)
            || ! class_exists(\DOMDocument::class)) {
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
        foreach ($xpath->query('//w:tbl') as $table) {
            $tableKey = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($table, $xpath)));

            if (in_array($documentType, ['venue_scale_request', 'venue_scale_appendix'], true)) {
                if (str_contains($tableKey, 'tensancon') && (str_contains($tableKey, 'tanggiam') || str_contains($tableKey, 'hinhthucthaydoi'))) {
                    $changed = $this->fillScaleCourtChangeTable($table, $xpath, $data) || $changed;
                    continue;
                }

                if (str_contains($tableKey, 'thongtinhientai') && str_contains($tableKey, 'thongtinsauthaydoi')) {
                    $changed = $this->fillScaleSummaryTable($table, $xpath, $data) || $changed;
                    continue;
                }
            }

            if ($documentType === 'venue_location_appendix'
                && str_contains($tableKey, 'noidungthongtin')
                && str_contains($tableKey, 'thongtindenghithaydoi')) {
                $changed = $this->fillLocationComparisonTable($table, $xpath, $data) || $changed;
            }
        }

        $serialized = $dom->saveXML();
        if (in_array($documentType, ['venue_scale_appendix', 'venue_location_appendix'], true)
            && (! str_contains($serialized, '{{signature_sportgo}}') || ! str_contains($serialized, '{{signature_owner}}'))) {
            $serialized = str_replace('</w:body>', $this->docxTwoPartySignatureTableXml() . '</w:body>', $serialized);
            $changed = true;
        }

        if ($changed) {
            $zip->addFromString($entry, $serialized);
        }

        $zip->close();
    }

    private function fillScaleCourtChangeTable(\DOMNode $table, \DOMXPath $xpath, array $data): bool
    {
        $rows = iterator_to_array($xpath->query('./w:tr', $table));
        if ($rows === []) {
            return false;
        }

        $headerIndex = null;
        foreach ($rows as $index => $row) {
            $key = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($row, $xpath)));
            if (str_contains($key, 'stt') && str_contains($key, 'tensancon')) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            return false;
        }

        $changes = $this->venueScaleCourtRows($data);
        $dataRows = array_values(array_slice($rows, $headerIndex + 1));
        if ($dataRows === []) {
            return false;
        }

        $changed = false;
        $hasApplyDateColumn = str_contains(Str::ascii($this->normalizeDocxLabel($this->docxNodeText($rows[$headerIndex], $xpath))), 'ngayapdung');

        while (count($dataRows) < count($changes)) {
            $clone = end($dataRows)->cloneNode(true);
            $table->appendChild($clone);
            $dataRows[] = $clone;
            $changed = true;
        }

        foreach ($dataRows as $index => $row) {
            if (! isset($changes[$index])) {
                $row->parentNode?->removeChild($row);
                $changed = true;
                continue;
            }

            $cells = $xpath->query('./w:tc', $row);
            if ($cells->length < 6) {
                continue;
            }

            $item = $changes[$index];
            $values = $hasApplyDateColumn
                ? [
                    (string) ($index + 1),
                    $item['name'],
                    $item['court_type'],
                    $item['change'],
                    $item['effective_date'],
                    $item['note'],
                ]
                : [
                    (string) ($index + 1),
                    $item['name'],
                    $item['court_type'],
                    $item['change'],
                    $item['status'],
                    $item['note'],
                ];

            foreach ($values as $cellIndex => $value) {
                $changed = $this->replaceDocxCellText($cells->item($cellIndex), $xpath, $value) || $changed;
            }
        }

        return $changed;
    }

    private function fillScaleSummaryTable(\DOMNode $table, \DOMXPath $xpath, array $data): bool
    {
        $values = $this->venueScaleSummaryValues($data);
        $changed = false;

        foreach ($xpath->query('./w:tr', $table) as $row) {
            $cells = $xpath->query('./w:tc', $row);
            if ($cells->length < 5) {
                continue;
            }

            $label = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($cells->item(1), $xpath)));
            foreach ($values as $needle => $rowValues) {
                if ($needle !== '' && str_contains($label, $needle)) {
                    $changed = $this->replaceDocxCellText($cells->item(2), $xpath, $rowValues[0]) || $changed;
                    $changed = $this->replaceDocxCellText($cells->item(3), $xpath, $rowValues[1]) || $changed;
                    $changed = $this->replaceDocxCellText($cells->item(4), $xpath, $rowValues[2]) || $changed;
                    break;
                }
            }
        }

        return $changed;
    }

    private function fillLocationComparisonTable(\DOMNode $table, \DOMXPath $xpath, array $data): bool
    {
        $values = $this->venueLocationComparisonValues($data);
        $changed = false;

        foreach ($xpath->query('./w:tr', $table) as $row) {
            $cells = $xpath->query('./w:tc', $row);
            if ($cells->length < 5) {
                continue;
            }

            $label = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($cells->item(1), $xpath)));
            foreach ($values as $needle => $rowValues) {
                if ($needle !== '' && str_contains($label, $needle)) {
                    $changed = $this->replaceDocxCellText($cells->item(2), $xpath, $rowValues[0]) || $changed;
                    $changed = $this->replaceDocxCellText($cells->item(3), $xpath, $rowValues[1]) || $changed;
                    $changed = $this->replaceDocxCellText($cells->item(4), $xpath, $rowValues[2]) || $changed;
                    break;
                }
            }
        }

        return $changed;
    }

    private function venueScaleCourtRows(array $data): array
    {
        $expectedDate = $this->formatDateForDocument($this->firstFilled($data, ['expected_effective_date', 'reviewed_at', 'submitted_at', 'rendered_at']))
            ?: now()->format('d/m/Y');
        $rows = [];

        $rawRows = data_get($data, 'court_change_rows', []);
        if (is_array($rawRows)) {
            foreach ($rawRows as $row) {
                if (is_array($row)) {
                    $rows[] = $this->normalizeScaleCourtRow($row, 'Điều chỉnh', 'Chờ SportGo duyệt', $expectedDate);
                }
            }
        }

        if ($rows === []) {
            $requestedRows = data_get($data, 'requested_court_rows', data_get($data, 'requested_courts', []));
            if (is_array($requestedRows)) {
                foreach ($requestedRows as $row) {
                    if (is_array($row)) {
                        $rows[] = $this->normalizeScaleCourtRow($row, 'Tăng/thêm sân', 'Dự kiến hoạt động sau phê duyệt', $expectedDate);
                    }
                }
            }

            $removedRows = data_get($data, 'removed_court_rows', []);
            if (is_array($removedRows)) {
                foreach ($removedRows as $row) {
                    if (is_array($row)) {
                        $rows[] = $this->normalizeScaleCourtRow($row, 'Giảm/ngừng khai thác', 'Dự kiến ngừng khai thác', $expectedDate);
                    }
                }
            }
        }

        if ($rows === [] && $this->firstFilled($data, ['new_courts_summary', 'requested_court_names', 'new_court_name'])) {
            $rows[] = [
                'name' => $this->firstFilled($data, ['new_courts_summary', 'requested_court_names', 'new_court_name']),
                'court_type' => $this->firstFilled($data, ['requested_court_type_name', 'court_type_name']) ?: 'Theo hồ sơ yêu cầu',
                'change' => $this->firstFilled($data, ['change_action']) ?: 'Tăng/thêm sân',
                'effective_date' => $expectedDate,
                'status' => 'Dự kiến hoạt động sau phê duyệt',
                'note' => $this->firstFilled($data, ['reason', 'status_reason']) ?: 'Theo đơn yêu cầu đã ký',
            ];
        }

        if ($rows === [] && $this->firstFilled($data, ['removed_courts_summary'])) {
            $rows[] = [
                'name' => $this->firstFilled($data, ['removed_courts_summary']),
                'court_type' => 'Theo danh sách sân hiện tại',
                'change' => 'Giảm/ngừng khai thác',
                'effective_date' => $expectedDate,
                'status' => 'Dự kiến ngừng khai thác',
                'note' => $this->firstFilled($data, ['reason', 'status_reason']) ?: 'Theo đơn yêu cầu đã ký',
            ];
        }

        return $rows ?: [[
            'name' => 'Theo danh sách sân trong hồ sơ yêu cầu',
            'court_type' => 'Theo cấu hình cụm sân',
            'change' => $this->firstFilled($data, ['change_action']) ?: 'Điều chỉnh',
            'effective_date' => $expectedDate,
            'status' => 'Chờ SportGo duyệt',
            'note' => $this->firstFilled($data, ['reason', 'status_reason']) ?: 'Theo đơn yêu cầu đã ký',
        ]];
    }

    private function normalizeScaleCourtRow(array $row, string $defaultChange, string $defaultStatus, string $defaultDate): array
    {
        $name = $this->cellPlainValue($row['name'] ?? $row['court_name'] ?? $row['title'] ?? '');
        $courtType = $this->cellPlainValue($row['court_type_name'] ?? $row['type_name'] ?? $row['court_type'] ?? '');
        $change = $this->cellPlainValue($row['change_action'] ?? $row['change'] ?? $row['action'] ?? '');
        $status = $this->cellPlainValue($row['status'] ?? $row['status_note'] ?? $row['expected_status'] ?? '');
        $note = $this->cellPlainValue($row['note'] ?? $row['status_note'] ?? $row['reason'] ?? '');

        return [
            'name' => $name !== '' ? $name : 'Theo danh sách sân trong hồ sơ',
            'court_type' => $courtType !== '' ? $courtType : 'Theo cấu hình cụm sân',
            'change' => $change !== '' ? $change : $defaultChange,
            'effective_date' => $this->formatDateForDocument($row['effective_date'] ?? $row['apply_date'] ?? null) ?: $defaultDate,
            'status' => $status !== '' ? $status : $defaultStatus,
            'note' => $note !== '' ? $note : 'Theo đơn yêu cầu đã ký',
        ];
    }

    private function venueScaleSummaryValues(array $data): array
    {
        $rows = $this->venueScaleCourtRows($data);
        $currentCount = $this->firstFilled($data, ['current_court_count', 'court_count_total', 'court_count']) ?: '0';
        $newCount = $this->numericDocumentValue($this->firstFilled($data, ['new_court_count', 'requested_court_count']))
            ?? collect($rows)->filter(fn (array $row): bool => ! str_contains(Str::ascii(Str::lower($row['change'])), 'giam'))->count();
        $removedCount = $this->numericDocumentValue($this->firstFilled($data, ['removed_court_count']))
            ?? collect($rows)->filter(fn (array $row): bool => str_contains(Str::ascii(Str::lower($row['change'])), 'giam'))->count();
        $afterCount = $this->scaleAfterCourtCount($currentCount, $newCount, $removedCount);
        $changedTypes = $this->scaleChangedCourtTypes($rows, $data);
        $currentTypes = $this->firstFilled($data, ['current_court_types_summary', 'court_types_summary']) ?: 'Theo danh sách sân hiện tại';
        $currentArea = $this->firstFilled($data, ['current_operation_area', 'venue_address', 'current_address', 'owner_address']) ?: 'Theo hồ sơ cụm sân hiện tại';
        $afterArea = $this->firstFilled($data, ['requested_operation_area', 'new_operation_area']) ?: 'Không thay đổi khu vực khai thác';
        $bookingImpact = $this->firstFilled($data, ['booking_impact']) ?: 'Rà soát booking còn hiệu lực trước khi cập nhật sân';
        $currentCountLabel = str_contains(Str::lower($currentCount), 'sân') ? $currentCount : $currentCount . ' sân';

        return [
            'tongsosancondangvanhanh' => [$currentCountLabel, $afterCount, 'Tính theo cụm sân được duyệt'],
            'sosancontangthem' => ['0 sân', $newCount . ' sân', $newCount > 0 ? 'Theo danh sách sân tăng thêm' : 'Không phát sinh'],
            'sosancongiamngungkhaithac' => ['0 sân', $removedCount . ' sân', $removedCount > 0 ? 'Theo danh sách sân ngừng khai thác' : 'Không phát sinh'],
            'loaisanmonthethaothaydoi' => [$currentTypes, $changedTypes, 'Theo từng sân con trong danh sách'],
            'khuvucdientichkhaithac' => [$currentArea, $afterArea, 'Kiểm tra theo hồ sơ minh chứng'],
            'trangthainhanbooking' => ['Đang nhận booking theo cấu hình hiện tại', $bookingImpact, 'Chủ sân chịu trách nhiệm xử lý lịch đã đặt'],
        ];
    }

    private function venueLocationComparisonValues(array $data): array
    {
        $currentAddress = $this->joinFilled([
            $this->firstFilled($data, ['current_address', 'venue_address']),
            $this->firstFilled($data, ['current_ward', 'venue_ward']),
            $this->firstFilled($data, ['current_province', 'venue_province']),
        ]) ?: 'Theo hồ sơ cụm sân hiện tại';
        $newAddress = $this->joinFilled([
            $this->firstFilled($data, ['new_address']),
            $this->firstFilled($data, ['new_ward']),
            $this->firstFilled($data, ['new_province']),
        ]) ?: 'Theo đơn yêu cầu thay đổi vị trí';
        $currentCoordinates = $this->coordinatesAndMap([
            'venue_latitude' => $this->firstFilled($data, ['current_latitude', 'venue_latitude']),
            'venue_longitude' => $this->firstFilled($data, ['current_longitude', 'venue_longitude']),
            'venue_map_url' => $this->firstFilled($data, ['current_map_url', 'venue_map_url']),
        ]) ?: 'Theo tọa độ đang lưu trên hệ thống';
        $newCoordinates = $this->coordinatesAndMap([
            'venue_latitude' => $this->firstFilled($data, ['new_latitude']),
            'venue_longitude' => $this->firstFilled($data, ['new_longitude']),
            'venue_map_url' => $this->firstFilled($data, ['new_map_url']),
        ]) ?: 'Theo tọa độ mới trong đơn yêu cầu';
        $currentRegion = $this->joinFilled([
            $this->firstFilled($data, ['current_ward', 'venue_ward']),
            $this->firstFilled($data, ['current_province', 'venue_province']),
        ]) ?: 'Theo địa chỉ hiện tại';
        $newRegion = $this->joinFilled([
            $this->firstFilled($data, ['new_ward']),
            $this->firstFilled($data, ['new_province']),
        ]) ?: 'Theo địa chỉ mới';
        $manager = $this->firstFilled($data, ['venue_manager_name', 'owner_signer_name', 'owner_full_name', 'business_name']) ?: 'Chủ sân/đối tác';
        $effectiveDate = $this->formatDateForDocument($this->firstFilled($data, ['expected_effective_date', 'reviewed_at', 'submitted_at', 'rendered_at']))
            ?: now()->format('d/m/Y');

        return [
            'diachicumsan' => [$currentAddress, $newAddress, 'Cập nhật sau khi phụ lục ký đủ'],
            'toadogpsduongdanbando' => [$currentCoordinates, $newCoordinates, 'Đã đối chiếu theo bản đồ'],
            'khuvucquanhuyentinhthanh' => [$currentRegion, $newRegion, 'Theo đơn vị hành chính đã chọn'],
            'sodienthoaitaicoso' => [$this->firstFilled($data, ['venue_phone', 'owner_phone']) ?: 'Theo hồ sơ hiện tại', $this->firstFilled($data, ['new_phone', 'venue_phone', 'owner_phone']) ?: 'Không thay đổi', 'Đối chiếu với hồ sơ liên hệ'],
            'nguoiquanlytaicoso' => [$manager, $manager, 'Không thay đổi người phụ trách'],
            'thoidiemdukienapdung' => ['Đang áp dụng', $effectiveDate, 'Sau khi SportGo và chủ sân ký phụ lục'],
        ];
    }

    private function scaleChangedCourtTypes(array $rows, array $data): string
    {
        $types = collect($rows)
            ->pluck('court_type')
            ->filter(fn (string $value): bool => $value !== '' && $value !== 'Theo cấu hình cụm sân')
            ->unique()
            ->values()
            ->implode('; ');

        return $types ?: ($this->firstFilled($data, ['requested_court_type_name', 'court_type_name', 'court_types_summary']) ?: 'Theo danh sách sân con thay đổi');
    }

    private function scaleAfterCourtCount(string $currentCount, int $newCount, int $removedCount): string
    {
        $current = $this->numericDocumentValue($currentCount);
        if ($current === null) {
            return trim($currentCount . ' + ' . $newCount . ' - ' . $removedCount) . ' sân';
        }

        return max(0, $current + $newCount - $removedCount) . ' sân';
    }

    private function numericDocumentValue(mixed $value): ?int
    {
        $text = $this->cellPlainValue($value);
        if ($text === '' || ! preg_match('/-?\d+/', $text, $match)) {
            return null;
        }

        return (int) $match[0];
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
                    $changed = $this->replaceDocxCellText($paragraph, $xpath, '{{owner_signer_name}}') || $changed;
                    break;
                }
            }
        }

        if (in_array($documentType, ['venue_scale_request', 'venue_location_change_request'], true)) {
            foreach ($xpath->query('//w:p') as $paragraph) {
                $ascii = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($paragraph, $xpath)));
                if (str_contains($ascii, 'nguoilamdon') || str_contains($ascii, 'kyghirohoten')) {
                    if (! str_contains($xml, '{{signature_owner}}')) {
                        $changed = $this->insertDocxParagraphAfter($paragraph, '{{owner_signer_name}}') || $changed;
                        $changed = $this->insertDocxParagraphAfter($paragraph, '{{signature_owner}}') || $changed;
                    }
                    break;
                }
            }
        }

        if (in_array($documentType, [
            'partner_contract',
            'venue_scale_appendix',
            'venue_location_appendix',
            'mutual_liquidation_minutes',
            'settlement_minutes',
        ], true)) {
            $tables = $xpath->query('//w:tbl');
            $signatureTable = $tables->item(max(0, $tables->length - 1));
            if ($signatureTable) {
                $changed = $this->ensureDocxTableBorders($signatureTable) || $changed;
                $changed = $this->centerDocxTableParagraphs($signatureTable, $xpath) || $changed;
                $rows = $xpath->query('./w:tr', $signatureTable);

                if (in_array($documentType, ['mutual_liquidation_minutes', 'settlement_minutes'], true)) {
                    $headingRow = $rows->item(0);
                    if ($headingRow) {
                        $headingCells = $xpath->query('./w:tc', $headingRow);
                        $leftHeading = $headingCells->length >= 1
                            ? Str::ascii($this->normalizeDocxLabel($this->docxNodeText($headingCells->item(0), $xpath)))
                            : '';

                        if ($headingCells->length >= 2 && str_contains($leftHeading, 'daidienbena')) {
                            $changed = $this->replaceDocxCellText($headingCells->item(0), $xpath, 'ĐẠI DIỆN BÊN A - SPORTGO') || $changed;
                            $changed = $this->replaceDocxCellText($headingCells->item(1), $xpath, 'ĐẠI DIỆN BÊN B - ĐỐI TÁC/CHỦ SÂN') || $changed;
                        }
                    }
                }

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
                        $changed = $this->replaceDocxCellText($cells->item(0), $xpath, '{{sportgo_signer_name}}') || $changed;
                        $changed = $this->replaceDocxCellText($cells->item(1), $xpath, '{{owner_signer_name}}') || $changed;
                    }
                }
            }
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function polishUnsignedSignaturePlaceholders(string $docxPath, string $documentType): void
    {
        if (! in_array($documentType, [
            'partner_application_form',
            'partner_contract',
            'venue_scale_request',
            'venue_location_change_request',
            'venue_scale_appendix',
            'venue_location_appendix',
            'termination_request',
            'termination_cancellation_request',
            'mutual_liquidation_minutes',
            'unilateral_termination_notice',
            'settlement_minutes',
        ], true) || ! is_file($docxPath) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $targets = [
            '{{signature_owner}}',
            '{{signature_sportgo}}',
            '{{owner_signer_name}}',
            '{{owner_signer_full_name}}',
            '{{sportgo_signer_name}}',
            '{{sportgo_signer_full_name}}',
        ];

        $this->styleSignatureRuns($docxPath, $targets, 'FFFFFF', true);
    }

    private function restoreSignedSignatureTextStyle(string $docxPath, GeneratedDocumentSignature $signature): void
    {
        $targets = array_filter([
            $signature->signer_full_name,
        ]);

        if ($targets !== []) {
            $this->styleSignatureRuns($docxPath, $targets, '000000', true, 26, 300);
        }
    }

    /**
     * Keep signature placeholders in the XML so TemplateProcessor can replace them,
     * but hide unsigned placeholders from the human preview and center the signature block.
     */
    private function styleSignatureRuns(
        string $docxPath,
        array $targets,
        string $color,
        bool $centerParagraph,
        ?int $fontSizeHalfPoints = null,
        ?int $lineSpacingTwips = null
    ): void
    {
        if (! is_file($docxPath) || ! class_exists(ZipArchive::class)) {
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

        $changed = false;
        $nextXml = preg_replace_callback('~<w:p\b[^>]*>.*?</w:p>~s', function (array $paragraphMatch) use ($targets, $color, $centerParagraph, $fontSizeHalfPoints, $lineSpacingTwips, &$changed): string {
            $paragraph = $paragraphMatch[0];
            $contains = false;

            foreach ($targets as $target) {
                if ($target !== '' && str_contains($paragraph, $this->xmlText((string) $target))) {
                    $contains = true;
                    break;
                }
                if ($target !== '' && str_contains($paragraph, (string) $target)) {
                    $contains = true;
                    break;
                }
            }

            if (! $contains) {
                return $paragraph;
            }

            $changed = true;
            if ($centerParagraph) {
                $paragraph = $this->setParagraphJustification($paragraph, 'center');
            }
            if ($lineSpacingTwips !== null) {
                $paragraph = $this->setParagraphLineSpacing($paragraph, $lineSpacingTwips);
            }

            return preg_replace_callback('~<w:r\b[^>]*>.*?</w:r>~s', function (array $runMatch) use ($targets, $color, $fontSizeHalfPoints): string {
                $run = $runMatch[0];
                foreach ($targets as $target) {
                    if ($target !== '' && (str_contains($run, $this->xmlText((string) $target)) || str_contains($run, (string) $target))) {
                        return $this->setRunTextAppearance($run, $color, $fontSizeHalfPoints);
                    }
                }

                return $run;
            }, $paragraph) ?? $paragraph;
        }, $xml);

        if ($changed && is_string($nextXml)) {
            $zip->addFromString($entry, $nextXml);
        }

        $zip->close();
    }

    private function setParagraphJustification(string $paragraphXml, string $value): string
    {
        if (preg_match('~<w:pPr\b[^>]*>.*?</w:pPr>~s', $paragraphXml)) {
            if (preg_match('~<w:jc\b[^>]*/>~', $paragraphXml)) {
                return preg_replace('~<w:jc\b[^>]*/>~', '<w:jc w:val="' . $value . '"/>', $paragraphXml, 1) ?? $paragraphXml;
            }

            return preg_replace('~(<w:pPr\b[^>]*>)~', '$1<w:jc w:val="' . $value . '"/>', $paragraphXml, 1) ?? $paragraphXml;
        }

        return preg_replace('~(<w:p\b[^>]*>)~', '$1<w:pPr><w:jc w:val="' . $value . '"/></w:pPr>', $paragraphXml, 1) ?? $paragraphXml;
    }

    private function setRunTextColor(string $runXml, string $color): string
    {
        $colorXml = '<w:color w:val="' . $color . '"/>';

        if (preg_match('~<w:rPr\b[^>]*>.*?</w:rPr>~s', $runXml)) {
            if (preg_match('~<w:color\b[^>]*/>~', $runXml)) {
                return preg_replace('~<w:color\b[^>]*/>~', $colorXml, $runXml, 1) ?? $runXml;
            }

            return preg_replace('~(<w:rPr\b[^>]*>)~', '$1' . $colorXml, $runXml, 1) ?? $runXml;
        }

        return preg_replace('~(<w:r\b[^>]*>)~', '$1<w:rPr>' . $colorXml . '</w:rPr>', $runXml, 1) ?? $runXml;
    }

    private function setRunTextAppearance(string $runXml, string $color, ?int $fontSizeHalfPoints): string
    {
        $runXml = $this->setRunTextColor($runXml, $color);
        if ($fontSizeHalfPoints === null) {
            return $runXml;
        }

        foreach (['sz', 'szCs'] as $property) {
            $sizeXml = '<w:' . $property . ' w:val="' . $fontSizeHalfPoints . '"/>';
            if (preg_match('~<w:' . $property . '\b[^>]*/>~', $runXml)) {
                $runXml = preg_replace('~<w:' . $property . '\b[^>]*/>~', $sizeXml, $runXml, 1) ?? $runXml;
            } elseif (preg_match('~<w:rPr\b[^>]*>.*?</w:rPr>~s', $runXml)) {
                $runXml = preg_replace('~(<w:rPr\b[^>]*>)~', '$1' . $sizeXml, $runXml, 1) ?? $runXml;
            }
        }

        return $runXml;
    }

    private function setParagraphLineSpacing(string $paragraphXml, int $lineSpacingTwips): string
    {
        $spacingXml = '<w:spacing w:before="0" w:after="0" w:line="' . $lineSpacingTwips . '" w:lineRule="auto"/>';
        if (preg_match('~<w:spacing\b[^>]*/>~', $paragraphXml)) {
            return preg_replace('~<w:spacing\b[^>]*/>~', $spacingXml, $paragraphXml, 1) ?? $paragraphXml;
        }
        if (preg_match('~<w:pPr\b[^>]*>.*?</w:pPr>~s', $paragraphXml)) {
            return preg_replace('~(<w:pPr\b[^>]*>)~', '$1' . $spacingXml, $paragraphXml, 1) ?? $paragraphXml;
        }

        return preg_replace('~(<w:p\b[^>]*>)~', '$1<w:pPr>' . $spacingXml . '</w:pPr>', $paragraphXml, 1) ?? $paragraphXml;
    }

    private function replaceResidualTemplateBlanks(string $docxPath, string $documentType): void
    {
        if (! in_array($documentType, [
            'partner_application_form',
            'partner_contract',
            'termination_request',
            'mutual_liquidation_minutes',
            'unilateral_termination_notice',
            'settlement_minutes',
            'venue_scale_request',
            'venue_location_change_request',
            'venue_scale_appendix',
            'venue_location_appendix',
        ], true) || ! class_exists(ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
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
        foreach ($xpath->query('//w:t') as $textNode) {
            $value = (string) $textNode->nodeValue;
            $trimmed = trim($value);

            if ($trimmed === '') {
                continue;
            }

            $replacement = $value;
            if (preg_match('/^[\.\s_…\/]{3,}$/u', $trimmed)) {
                $replacement = 'Chưa cung cấp';
            } else {
                $replacement = preg_replace('/\.{6,}/u', 'Chưa cung cấp', $replacement);
                $replacement = preg_replace('/…{2,}/u', 'Chưa cung cấp', $replacement);
            }

            if ($replacement !== $value) {
                $textNode->nodeValue = '';
                $textNode->appendChild($dom->createTextNode($replacement));
                $textNode->setAttribute('xml:space', 'preserve');
                $changed = true;
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

            if (in_array($documentType, ['venue_scale_request', 'venue_location_change_request', 'venue_scale_appendix', 'venue_location_appendix'], true)
                && $this->isInsideDocxTable($paragraph)) {
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

    private function replaceVenueChangeReasonSection(string $docxPath, array $data, string $documentType): void
    {
        if (! in_array($documentType, ['venue_scale_request', 'venue_location_change_request'], true)
            || ! class_exists(ZipArchive::class)
            || ! class_exists(\DOMDocument::class)) {
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
        $reason = $this->firstFilled($data, ['reason', 'note', 'status_reason']) ?: 'Chưa cung cấp';
        foreach ($xpath->query('//w:p') as $paragraph) {
            $key = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($paragraph, $xpath)));
            if (! str_contains($key, 'lydoyeucauthaydoiquymo') && ! str_contains($key, 'lydoyeucauthaydoivitri')) {
                continue;
            }

            $next = $paragraph->nextSibling;
            $removed = 0;
            while ($next && $removed < 4) {
                $candidate = $next;
                $next = $next->nextSibling;

                if (! $candidate instanceof \DOMElement || $candidate->localName !== 'p') {
                    continue;
                }

                $text = trim($this->docxNodeText($candidate, $xpath));
                if (! preg_match('/^[\.\s_…\/]{3,}$/u', $text)) {
                    break;
                }

                $candidate->parentNode?->removeChild($candidate);
                $removed++;
                $changed = true;
            }

            $nextParagraph = $paragraph->nextSibling;
            $nextText = $nextParagraph instanceof \DOMElement && $nextParagraph->localName === 'p'
                ? trim($this->docxNodeText($nextParagraph, $xpath))
                : '';
            if ($nextText !== $reason) {
                $reasonParagraph = $this->createDocumentTextParagraph($dom, $reason);
                $paragraph->parentNode?->insertBefore($reasonParagraph, $paragraph->nextSibling);
                $changed = true;
            }

            break;
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function replaceVenueChangeAttachmentSection(string $docxPath, array $data, string $documentType): void
    {
        if (! in_array($documentType, [
            'venue_scale_request',
            'venue_location_change_request',
            'venue_scale_appendix',
            'venue_location_appendix',
        ], true) || ! class_exists(ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $entry = 'word/document.xml';
        $xml = $zip->getFromName($entry);
        if (! is_string($xml) || trim($xml) === '') {
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
        $attachments = $this->venueChangeAttachmentNames($data);
        $changed = false;

        foreach ($xpath->query('/w:document/w:body/w:p') as $heading) {
            $headingKey = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($heading, $xpath)));
            if (! str_contains($headingKey, 'danhmuctailieuminhchungkemtheo')
                && ! str_contains($headingKey, 'hosotailieuminhchunggukem')
                && ! str_contains($headingKey, 'tailieuminhchungguikem')) {
                continue;
            }

            $nextSection = null;
            $candidates = [];
            for ($sibling = $heading->nextSibling; $sibling; $sibling = $sibling->nextSibling) {
                if (! $sibling instanceof \DOMElement || $sibling->namespaceURI !== 'http://schemas.openxmlformats.org/wordprocessingml/2006/main') {
                    continue;
                }
                if ($sibling->localName !== 'p') {
                    $nextSection = $sibling;
                    break;
                }

                $text = trim($this->docxNodeText($sibling, $xpath));
                if ($text !== '' && preg_match('/^(?:[IVXLCDM]+\.|\d+\.)\s+/u', $text)) {
                    $nextSection = $sibling;
                    break;
                }
                $candidates[] = $sibling;
            }

            foreach ($candidates as $candidate) {
                $candidate->parentNode?->removeChild($candidate);
                $changed = true;
            }

            $lines = $attachments !== []
                ? array_map(
                    fn (string $name, int $index): string => sprintf('Tệp %02d: %s', $index + 1, $name),
                    $attachments,
                    array_keys($attachments)
                )
                : ['Không có tệp đính kèm được ghi nhận trên hệ thống.'];
            foreach ($lines as $line) {
                $paragraph = $this->createDocumentTextParagraph($dom, $line);
                if ($nextSection) {
                    $nextSection->parentNode?->insertBefore($paragraph, $nextSection);
                } else {
                    $heading->parentNode?->appendChild($paragraph);
                }
                $changed = true;
            }

            break;
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }
        $zip->close();
    }

    /** @return array<int, string> */
    private function venueChangeAttachmentNames(array $data): array
    {
        $names = [];
        $collect = function (mixed $value) use (&$collect, &$names): void {
            if (is_array($value)) {
                foreach (['file_name', 'original_name', 'name'] as $key) {
                    if (isset($value[$key]) && is_scalar($value[$key])) {
                        $names[] = trim((string) $value[$key]);

                        return;
                    }
                }
                foreach ($value as $item) {
                    $collect($item);
                }

                return;
            }
            if (! is_scalar($value)) {
                return;
            }
            foreach (preg_split('/[;\r\n]+/u', (string) $value) ?: [] as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        };

        $collect($data['attachment_list'] ?? null);
        $collect($data['supplementary_documents'] ?? null);
        $evidenceName = trim((string) ($data['evidence_file_name'] ?? ''));
        if ($evidenceName !== '') {
            $names[] = 'Ảnh minh chứng quy mô sân: ' . $evidenceName;
        } elseif (! empty($data['evidence_present'])) {
            $names[] = 'Ảnh minh chứng quy mô sân đã tải lên hệ thống';
        }

        $unique = [];
        foreach ($names as $name) {
            $key = mb_strtolower($name, 'UTF-8');
            if ($name !== '' && ! isset($unique[$key])) {
                $unique[$key] = $name;
            }
        }

        return array_values($unique);
    }

    private function createDocumentTextParagraph(\DOMDocument $dom, string $text): \DOMElement
    {
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $paragraph = $dom->createElementNS($namespace, 'w:p');
        $properties = $dom->createElementNS($namespace, 'w:pPr');
        $spacing = $dom->createElementNS($namespace, 'w:spacing');
        $spacing->setAttributeNS($namespace, 'w:before', '0');
        $spacing->setAttributeNS($namespace, 'w:after', '60');
        $spacing->setAttributeNS($namespace, 'w:line', '300');
        $spacing->setAttributeNS($namespace, 'w:lineRule', 'exact');
        $properties->appendChild($spacing);
        $paragraph->appendChild($properties);

        $run = $dom->createElementNS($namespace, 'w:r');
        $runProperties = $dom->createElementNS($namespace, 'w:rPr');
        $fonts = $dom->createElementNS($namespace, 'w:rFonts');
        foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $attribute) {
            $fonts->setAttributeNS($namespace, 'w:' . $attribute, 'Times New Roman');
        }
        $runProperties->appendChild($fonts);
        foreach (['sz', 'szCs'] as $sizeName) {
            $size = $dom->createElementNS($namespace, 'w:' . $sizeName);
            $size->setAttributeNS($namespace, 'w:val', '26');
            $runProperties->appendChild($size);
        }
        $run->appendChild($runProperties);
        $textNode = $dom->createElementNS($namespace, 'w:t');
        $textNode->setAttribute('xml:space', 'preserve');
        $textNode->appendChild($dom->createTextNode($text));
        $run->appendChild($textNode);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    private function inlineReplacementText(string $text, array $data, string $documentType): ?string
    {
        $normalized = $this->normalizeDocxLabel($text);
        $ascii = Str::ascii($normalized);
        $place = $this->firstFilled($data, ['document_place', 'venue_province']) ?: 'Hà Nội';
        [$day, $month, $year] = $this->documentDateParts($data);

        if (in_array($documentType, ['venue_scale_request', 'venue_location_change_request', 'venue_scale_appendix', 'venue_location_appendix'], true)
            && str_contains($ascii, 'kemtheo')) {
            return $this->venueChangeInlineReplacementText($text, $data, $documentType);
        }

        if ($documentType === 'unilateral_termination_notice') {
            $noticeCode = $this->firstFilled($data, ['notice_code', 'document_number', 'document_code']) ?: 'CV-SG';
            $receiver = $this->firstFilled($data, ['receiver_name', 'business_name', 'venue_owner_name']) ?: 'Đối tác/chủ sân';
            $contractCode = $this->firstFilled($data, ['contract_code']) ?: 'Chưa cung cấp';
            $contractSignedAt = $this->firstFilled($data, ['contract_signed_at']) ?: 'theo hồ sơ ký số';
            $deadline = $this->firstFilled($data, ['settlement_deadline', 'effective_termination_date', 'effective_date']) ?: 'theo thời hạn trên công văn';

            if (str_starts_with($ascii, 'so') && str_contains($ascii, 'cvsg')) {
                return "Số: {$noticeCode}/CV-SG V/v chấm dứt hợp tác đối tác SportGo";
            }

            if (str_contains($ascii, 'kinhgui')) {
                return "Kính gửi: {$receiver}";
            }

            if (str_contains($ascii, 'hopdonghoptacdoitacsportgoso') && str_contains($ascii, 'giuasportgovadoitac')) {
                return "• Hợp đồng hợp tác đối tác SportGo số {$contractCode}, ký ngày {$contractSignedAt} giữa SportGo và đối tác/chủ sân.";
            }

            if (str_contains($ascii, 'cungcapbosungchungtu') && str_contains($ascii, 'ketungaynhancongvan')) {
                return "• Đối tác/chủ sân cung cấp bổ sung chứng từ, tài khoản nhận tiền, thông tin người phụ trách và tài liệu cần thiết trước {$deadline}.";
            }

            if (str_contains($ascii, 'truoc') && str_contains($ascii, 'desportgoxemxettruockhihoantat')) {
                return "Trường hợp đối tác/chủ sân có ý kiến phản hồi hoặc tài liệu chứng minh khác, đề nghị gửi bằng văn bản hoặc qua kênh hỗ trợ chính thức của SportGo trước {$deadline} để SportGo xem xét trước khi hoàn tất bước chấm dứt/thu hồi quyền.";
            }
        }

        if (str_contains($ascii, 'ngay') && str_contains($ascii, 'thang') && str_contains($ascii, 'nam') && ! str_contains($ascii, 'kemtheo')) {
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

            if (str_contains($ascii, 'hopdongduoclapthanh') && str_contains($ascii, 'bangiay')) {
                return '• Hợp đồng được lập thành 02 bản giấy có giá trị như nhau hoặc được lưu dưới dạng điện tử trên hệ thống SportGo, mỗi Bên giữ/truy cập một bản.';
            }
        }

        if ($documentType === 'settlement_minutes') {
            $contractCode = $this->firstFilled($data, ['contract_code', 'contract_number']) ?: 'Theo hợp đồng trên hệ thống';
            $terminationCode = $this->firstFilled($data, ['termination_request_code', 'termination_code']) ?: 'Theo hồ sơ chấm dứt';
            $deadline = $this->firstFilled($data, ['settlement_deadline', 'effective_termination_date']) ?: 'Theo thời hạn trên hệ thống';
            $copyCount = $this->firstFilled($data, ['document_copy_count']) ?: '02';
            $eachPartyCopyCount = $this->firstFilled($data, ['each_party_copy_count']) ?: '01';

            if (str_contains($ascii, 'cancuhopdonghoptacdoitacsportgoso') && str_contains($ascii, 'hdhtsg')) {
                return "Căn cứ Hợp đồng hợp tác đối tác SportGo số {$contractCode}; căn cứ hồ sơ chấm dứt {$terminationCode}; căn cứ dữ liệu booking, thanh toán, số dư chủ sân, phí nền tảng và các nghĩa vụ phát sinh trên hệ thống SportGo, các bên lập biên bản quyết toán như sau:";
            }

            if (str_contains($ascii, 'chamnhatngay') && str_contains($ascii, 'chuacungcap')) {
                return "Chậm nhất {$deadline}";
            }

            if (str_contains($ascii, 'bienbannayduoclapthanh')) {
                return "Biên bản này được lập thành {$copyCount} bản có giá trị như nhau, mỗi bên giữ {$eachPartyCopyCount} bản; hoặc được lưu dưới dạng điện tử có xác nhận hợp lệ của các bên.";
            }
        }

        if (in_array($documentType, ['venue_scale_request', 'venue_location_change_request', 'venue_scale_appendix', 'venue_location_appendix'], true)) {
            return $this->venueChangeInlineReplacementText($text, $data, $documentType);
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
    private function fillTwoColumnTemplateBodyFields(string $docxPath, array $fields, string $documentType): void
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
                if ($cells->length !== 2) {
                    continue;
                }

                $firstCellKey = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($cells->item(0), $xpath)));
                $secondCellKey = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($cells->item(1), $xpath)));
                if (str_contains($firstCellKey, 'daidienbena') && str_contains($secondCellKey, 'daidienbenb')) {
                    continue;
                }

                for ($cellIndex = 0; $cellIndex < $cells->length - 1; $cellIndex++) {
                    $label = $this->normalizeDocxLabel($this->docxNodeText($cells->item($cellIndex), $xpath));
                    if ($label === '') {
                        continue;
                    }

                    $matchedValue = $this->matchedDocxFieldValue($label, $normalizedFields);
                    if ($matchedValue !== null) {
                        $valueCell = $cells->item($cellIndex + 1);
                        $changed = $this->replaceDocxCellText($valueCell, $xpath, $matchedValue) || $changed;
                        if ($documentType === 'settlement_minutes') {
                            $changed = $this->alignDocxCellParagraphs($valueCell, $xpath, 'left') || $changed;
                        }
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

    private function alignDocxCellParagraphs(\DOMNode $cell, \DOMXPath $xpath, string $alignment): bool
    {
        $document = $cell->ownerDocument;
        if (! $document) {
            return false;
        }

        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $changed = false;
        foreach ($xpath->query('.//w:p', $cell) as $paragraph) {
            $paragraphProperties = null;
            foreach ($paragraph->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'pPr') {
                    $paragraphProperties = $child;
                    break;
                }
            }

            if (! $paragraphProperties) {
                $paragraphProperties = $document->createElementNS($namespace, 'w:pPr');
                $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
            }

            foreach (iterator_to_array($paragraphProperties->childNodes) as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'jc') {
                    $paragraphProperties->removeChild($child);
                }
            }

            $justification = $document->createElementNS($namespace, 'w:jc');
            $justification->setAttributeNS($namespace, 'w:val', $alignment);
            $paragraphProperties->appendChild($justification);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Prefer exact DOCX labels and longer labels first so short labels cannot
     * fill more specific owner/applicant rows by accident.
     *
     * @param  array<string, string>  $normalizedFields
     */
    private function matchedDocxFieldValue(string $label, array $normalizedFields): ?string
    {
        if (array_key_exists($label, $normalizedFields)) {
            return $normalizedFields[$label];
        }

        uksort($normalizedFields, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($normalizedFields as $needle => $value) {
            if ($needle !== '' && str_contains($label, $needle)) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeTwoColumnTableWidths(string $docxPath): void
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
        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $changed = false;

        $tableIndex = 0;
        foreach ($xpath->query('//w:tbl') as $table) {
            $hasTwoColumnRow = false;
            $hasWiderRow = false;
            foreach ($xpath->query('./w:tr', $table) as $row) {
                $cellCount = $xpath->query('./w:tc', $row)->length;
                if ($cellCount === 2) {
                    $hasTwoColumnRow = true;
                } elseif ($cellCount > 2) {
                    $hasWiderRow = true;
                }
            }

            if (! $hasTwoColumnRow || $hasWiderRow) {
                continue;
            }

            $changed = $this->setDocxTableWidth($table, $namespace, '8800') || $changed;
            $changed = $this->setDocxTableGridWidths($table, $namespace, ['2600', '6200']) || $changed;
            if ($tableIndex === 0) {
                $changed = $this->centerDocxFirstTableRow($table, $xpath, $namespace) || $changed;
            }
            foreach ($xpath->query('./w:tr', $table) as $row) {
                $cells = $xpath->query('./w:tc', $row);
                if ($cells->length !== 2) {
                    continue;
                }

                $changed = $this->setDocxCellWidth($cells->item(0), $namespace, '2600') || $changed;
                $changed = $this->setDocxCellWidth($cells->item(1), $namespace, '6200') || $changed;
            }
            $tableIndex++;
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function centerDocxFirstTableRow(\DOMNode $table, \DOMXPath $xpath, string $namespace): bool
    {
        $document = $table->ownerDocument;
        if (! $document) {
            return false;
        }

        $changed = false;
        foreach ($xpath->query('./w:tr[1]//w:p', $table) as $paragraph) {
            $paragraphProperties = null;
            foreach ($paragraph->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'pPr') {
                    $paragraphProperties = $child;
                    break;
                }
            }

            if (! $paragraphProperties) {
                $paragraphProperties = $document->createElementNS($namespace, 'w:pPr');
                $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
            }

            foreach (iterator_to_array($paragraphProperties->childNodes) as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'jc') {
                    $paragraphProperties->removeChild($child);
                }
            }

            $justification = $document->createElementNS($namespace, 'w:jc');
            $justification->setAttributeNS($namespace, 'w:val', 'center');
            $paragraphProperties->appendChild($justification);
            $changed = true;
        }

        return $changed;
    }

    private function setDocxTableWidth(\DOMNode $table, string $namespace, string $width): bool
    {
        $document = $table->ownerDocument;
        if (! $document) {
            return false;
        }

        $tableProperties = null;
        foreach ($table->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tblPr') {
                $tableProperties = $child;
                break;
            }
        }

        if (! $tableProperties) {
            $tableProperties = $document->createElementNS($namespace, 'w:tblPr');
            $table->insertBefore($tableProperties, $table->firstChild);
        }

        $tableWidth = null;
        $tableLayout = null;
        foreach ($tableProperties->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'tblW') {
                $tableWidth = $child;
            }
            if ($child->localName === 'tblLayout') {
                $tableLayout = $child;
            }
        }

        if (! $tableWidth) {
            $tableWidth = $document->createElementNS($namespace, 'w:tblW');
            $tableProperties->appendChild($tableWidth);
        }
        $tableWidth->setAttributeNS($namespace, 'w:w', $width);
        $tableWidth->setAttributeNS($namespace, 'w:type', 'dxa');

        if (! $tableLayout) {
            $tableLayout = $document->createElementNS($namespace, 'w:tblLayout');
            $tableProperties->appendChild($tableLayout);
        }
        $tableLayout->setAttributeNS($namespace, 'w:type', 'fixed');

        return true;
    }

    private function setDocxTableGridWidths(\DOMNode $table, string $namespace, array $widths): bool
    {
        $document = $table->ownerDocument;
        if (! $document) {
            return false;
        }

        foreach (iterator_to_array($table->childNodes) as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tblGrid') {
                $table->removeChild($child);
            }
        }

        $grid = $document->createElementNS($namespace, 'w:tblGrid');
        foreach ($widths as $width) {
            $column = $document->createElementNS($namespace, 'w:gridCol');
            $column->setAttributeNS($namespace, 'w:w', $width);
            $grid->appendChild($column);
        }

        $insertBefore = null;
        foreach ($table->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tr') {
                $insertBefore = $child;
                break;
            }
        }

        $insertBefore ? $table->insertBefore($grid, $insertBefore) : $table->appendChild($grid);

        return true;
    }

    private function setDocxCellWidth(\DOMNode $cell, string $namespace, string $width): bool
    {
        $document = $cell->ownerDocument;
        if (! $document) {
            return false;
        }

        $tcPr = null;
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tcPr') {
                $tcPr = $child;
                break;
            }
        }

        if (! $tcPr) {
            $tcPr = $document->createElementNS($namespace, 'w:tcPr');
            $cell->insertBefore($tcPr, $cell->firstChild);
        }

        $tcW = null;
        foreach ($tcPr->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tcW') {
                $tcW = $child;
                break;
            }
        }

        if (! $tcW) {
            $tcW = $document->createElementNS($namespace, 'w:tcW');
            $tcPr->appendChild($tcW);
        }

        $tcW->setAttributeNS($namespace, 'w:w', $width);
        $tcW->setAttributeNS($namespace, 'w:type', 'dxa');

        return true;
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
            'Tên đơn vị' => $this->firstFilled($data, ['sportgo_company_name']) ?: 'Công ty TNHH SportGo',
            'Mã số thuế/ĐKKD' => $this->firstFilled($data, ['sportgo_tax_code']) ?: '0000000000',
            'Địa chỉ trụ sở' => $this->firstFilled($data, ['sportgo_address']) ?: config('app.url'),
            'Người đại diện' => $this->firstFilled($data, ['sportgo_representative_name', 'sportgo_representative']) ?: 'Đại diện SportGo',
            'Chức vụ' => $this->firstFilled($data, ['sportgo_representative_title', 'sportgo_representative_position']) ?: 'Đại diện pháp lý',
            'Căn cứ đại diện/ủy quyền' => $this->firstFilled($data, ['sportgo_authorization_basis']) ?: 'Người đại diện theo pháp luật',
            'Số điện thoại/Email' => $this->joinFilled([
                $this->firstFilled($data, ['sportgo_phone']),
                $this->firstFilled($data, ['sportgo_email']),
            ], ' - ') ?: 'Chưa cung cấp',
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
                'Ngày cấp - Nơi cấp' => $this->issuedInfo($data) ?: 'Chưa cung cấp',
                'Địa chỉ liên hệ/trụ sở' => $this->firstFilled($data, ['party_b_address', 'business_address', 'venue_address']),
                'Người đại diện nếu là tổ chức' => $businessName ? $applicantName : 'Không áp dụng',
                'Chức vụ/Quan hệ đại diện' => $this->firstFilled($data, ['representative_position']) ?: ($businessName ? 'Người đại diện' : 'Chủ cơ sở'),
                'Số điện thoại/Email' => $this->joinFilled([$phone, $email], ' - '),
                'Tài khoản nhận tiền' => $this->firstFilled($data, ['bank_account_snapshot']) ?: $this->joinFilled([
                    $this->firstFilled($data, ['bank_name']),
                    $this->firstFilled($data, ['account_number']),
                    $this->firstFilled($data, ['account_holder_name']),
                ], ' - ') ?: 'Chưa cung cấp',
            ],
            3 => [
                'Tên cụm sân' => $this->firstFilled($data, ['venue_name', 'venue_cluster_list']),
                'Mã cụm sân trên hệ thống' => $this->firstFilled($data, ['venue_cluster_code', 'venue_cluster_id']) ?: 'Tạo sau khi hợp đồng hoàn tất',
                'Địa chỉ cụm sân' => $this->firstFilled($data, ['venue_address']),
                'Loại sân/môn thể thao' => $this->firstFilled($data, ['court_types_summary', 'court_types']),
                'Số lượng sân con' => $this->firstFilled($data, ['court_count_total', 'court_count']) ?: 'Chưa cung cấp',
                'Thời gian hoạt động' => $this->firstFilled($data, ['expected_opening_hours']) ?: 'Theo cấu hình vận hành trên SportGo',
            ],
        ];
    }

    private function venueChangeRequestTemplateBodyValues(array $data, string $documentType): array
    {
        $ownerName = $this->venueChangeOwnerDisplayName($data);
        $legalId = $this->venueChangeLegalId($data);
        $clusterAddress = $this->joinFilled([
            $this->firstFilled($data, ['venue_address', 'current_address']),
            $this->firstFilled($data, ['venue_ward', 'current_ward']),
            $this->firstFilled($data, ['venue_province', 'current_province']),
        ]);
        $bookingImpact = $this->firstFilled($data, ['booking_impact']) ?: 'Không ảnh hưởng; rà soát booking còn hiệu lực trước khi cập nhật.';
        $ownerResponsible = $this->venueChangeOwnerDisplayName($data) ?: 'Chủ sân/đối tác';

        $common = [
            'Mã yêu cầu trên hệ thống' => $this->firstFilled($data, ['request_code', 'request_id', 'source_document_code', 'document_code']),
            'Tên chủ sân/đối tác' => $ownerName,
            'Họ tên/Tên tổ chức' => $ownerName,
            'Số CCCD/CMND/Hộ chiếu hoặc mã số kinh doanh' => $legalId,
            'Số điện thoại' => $this->firstFilled($data, ['owner_phone', 'phone', 'venue_phone']),
            'Email' => $this->firstFilled($data, ['owner_email', 'email']),
            'Địa chỉ liên hệ' => $this->firstFilled($data, ['owner_address', 'business_address', 'contact_address']),
            'Tên chi nhánh/cụm sân' => $this->firstFilled($data, ['venue_name', 'cluster_name']),
            'Mã chi nhánh/cụm sân' => $this->firstFilled($data, ['venue_cluster_code', 'venue_cluster_id', 'cluster_code']),
            'Mã chi nhánh/cụm sân trên hệ thống' => $this->firstFilled($data, ['venue_cluster_code', 'venue_cluster_id', 'cluster_code']),
            'Số hợp đồng/thoả thuận hợp tác' => $this->firstFilled($data, ['contract_code', 'contract_number']) ?: 'Chưa có/không áp dụng',
            'Số hợp đồng/thỏa thuận hợp tác' => $this->firstFilled($data, ['contract_code', 'contract_number']) ?: 'Chưa có/không áp dụng',
            'Số hợp đồng hợp tác' => $this->firstFilled($data, ['contract_code', 'contract_number']) ?: 'Chưa có/không áp dụng',
            'Ngày ký hợp đồng' => $this->formatDateForDocument($this->firstFilled($data, ['contract_signed_at', 'contract_signed_date'])) ?: 'Chưa cung cấp',
            'Ngày dự kiến áp dụng thay đổi' => $this->formatDateForDocument($this->firstFilled($data, ['expected_effective_date', 'reviewed_at', 'submitted_at', 'rendered_at'])),
            'Người phụ trách liên hệ' => $this->joinFilled([
                $this->firstFilled($data, ['venue_manager_name', 'owner_signer_name', 'owner_full_name']),
                $this->firstFilled($data, ['owner_phone', 'venue_phone']),
                $this->firstFilled($data, ['owner_email', 'venue_email']),
            ], ' - '),
            'Booking/lịch đặt sân đang còn hiệu lực' => $bookingImpact,
            'Phương án xử lý lịch đã đặt' => 'Giữ lịch, chuyển sân hoặc liên hệ khách hàng theo phương án đã thông báo.',
            'Phương án xử lý booking bị ảnh hưởng' => 'Giữ lịch, chuyển lịch, hoàn tiền hoặc liên hệ khách hàng theo phương án đã thông báo.',
            'Cấu hình giá và khung giờ' => 'Giữ nguyên; cập nhật bảng giá/khung giờ nếu quy mô hoặc vị trí mới yêu cầu.',
            'Trạng thái vận hành sau thay đổi' => 'Chờ duyệt/phát hành phụ lục, sau đó cập nhật theo trạng thái thực tế.',
            'Thời gian tạm dừng nhận lịch mới nếu có' => 'Không tạm dừng, trừ khi SportGo yêu cầu trong quá trình cập nhật.',
            'Bộ phận/cá nhân chịu trách nhiệm phối hợp' => "Chủ sân/đối tác: {$ownerResponsible}; SportGo: Bộ phận tiếp nhận SportGo",
        ];

        if (in_array($documentType, ['venue_scale_request', 'venue_scale_appendix'], true)) {
            return [
                ...$common,
                'Quy mô/số lượng sân con hiện tại' => $this->firstFilled($data, ['current_court_count', 'court_count_total', 'court_count']),
                'Hình thức thay đổi' => $this->firstFilled($data, ['change_action', 'change_type']) ?: 'Mở rộng quy mô/thêm sân con',
                'Số lượng sân con tăng/giảm' => $this->firstFilled($data, ['change_court_count', 'requested_court_count']) ?: '1',
                'Loại sân/môn thể thao thay đổi' => $this->firstFilled($data, ['requested_court_type_name', 'court_type_name', 'court_types_summary']),
                'Tên/danh sách sân con dự kiến' => $this->firstFilled($data, ['requested_court_names', 'new_court_name', 'court_name', 'courts_summary']),
                'Thời điểm dự kiến áp dụng' => $this->formatDateForDocument($this->firstFilled($data, ['expected_effective_date', 'submitted_at', 'rendered_at'])),
            ];
        }

        return [
            ...$common,
            'Địa chỉ/tọa độ hiện tại' => $this->joinFilled([
                $clusterAddress,
                $this->coordinatesAndMap([
                    'venue_latitude' => $this->firstFilled($data, ['current_latitude', 'venue_latitude']),
                    'venue_longitude' => $this->firstFilled($data, ['current_longitude', 'venue_longitude']),
                    'venue_map_url' => $this->firstFilled($data, ['current_map_url', 'venue_map_url']),
                ]),
            ], ' - '),
            'Địa chỉ mới' => $this->joinFilled([
                $this->firstFilled($data, ['new_address']),
                $this->firstFilled($data, ['new_ward']),
                $this->firstFilled($data, ['new_province']),
            ]),
            'Tọa độ/đường dẫn bản đồ mới' => $this->coordinatesAndMap([
                'venue_latitude' => $this->firstFilled($data, ['new_latitude']),
                'venue_longitude' => $this->firstFilled($data, ['new_longitude']),
                'venue_map_url' => $this->firstFilled($data, ['new_map_url']),
            ]),
            'Số điện thoại liên hệ tại vị trí mới' => $this->firstFilled($data, ['new_phone', 'venue_phone', 'owner_phone']),
            'Thời điểm dự kiến áp dụng' => $this->formatDateForDocument($this->firstFilled($data, ['expected_effective_date', 'submitted_at', 'rendered_at'])),
            'Phạm vi ảnh hưởng đến lịch đặt sân/booking hiện có' => $this->firstFilled($data, ['booking_impact']) ?: 'Chủ sân cam kết tự xử lý các lịch đặt bị ảnh hưởng và thông báo cho khách hàng nếu phát sinh.',
        ];
    }

    private function venueChangeInlineReplacementText(string $text, array $data, string $documentType): ?string
    {
        $normalized = $this->normalizeDocxLabel($text);
        $ascii = Str::ascii($normalized);

        if (in_array($documentType, ['venue_scale_appendix', 'venue_location_appendix'], true)
            && preg_match('/^phuluc(?:[ivxlcdm0-9]+)?$/i', $ascii)) {
            $appendixNumber = $this->firstFilled($data, ['appendix_number']) ?: 'I';
            return 'Phụ lục ' . $appendixNumber;
        }

        if (str_contains($ascii, 'kinhgui')) {
            return 'Kính gửi: Công ty/Đơn vị vận hành nền tảng SportGo';
        }

        if (str_contains($ascii, 'lydoyeucauthaydoiquymo')) {
            return '5. Lý do yêu cầu thay đổi quy mô';
        }

        if (str_contains($ascii, 'lydoyeucauthaydoivitri')) {
            return '4. Lý do yêu cầu thay đổi vị trí';
        }

        $requestCode = $this->firstFilled($data, ['request_code', 'request_id', 'source_document_code', 'document_code']) ?: 'theo hệ thống';
        $effectiveDate = $this->formatDateForDocument($this->firstFilled($data, ['expected_effective_date', 'reviewed_at', 'submitted_at', 'rendered_at'])) ?: now()->format('d/m/Y');
        if ($documentType === 'venue_scale_appendix' && str_contains($ascii, 'kemtheodonyeucauthaydoiquymo')) {
            return "(Kèm theo Đơn yêu cầu thay đổi quy mô sân số: {$requestCode} ngày {$effectiveDate} của Chủ sân/Đối tác)";
        }

        if ($documentType === 'venue_location_appendix' && str_contains($ascii, 'kemtheodonyeucauthaydoivitri')) {
            return "(Kèm theo Đơn yêu cầu thay đổi vị trí cụm sân số: {$requestCode} ngày {$effectiveDate} của Chủ sân/Đối tác)";
        }

        $bookingImpact = $this->firstFilled($data, ['booking_impact']) ?: 'Không ảnh hưởng; rà soát booking còn hiệu lực trước khi cập nhật.';
        if (str_contains($ascii, 'bookinglichdatsandangconhieuluc')) {
            return 'Booking/lịch đặt sân đang còn hiệu lực: ' . $bookingImpact;
        }

        if (str_contains($ascii, 'phuonganxulylichdadat')) {
            return 'Phương án xử lý lịch đã đặt: Giữ lịch, chuyển sân hoặc liên hệ khách hàng theo phương án đã thông báo.';
        }

        if (str_contains($ascii, 'phuonganxulybookingbia')) {
            return 'Phương án xử lý booking bị ảnh hưởng: Giữ lịch, chuyển lịch, hoàn tiền hoặc liên hệ khách hàng theo phương án đã thông báo.';
        }

        if (str_contains($ascii, 'cauhinhgiavakhunggio')) {
            return 'Cấu hình giá và khung giờ: Giữ nguyên; cập nhật bảng giá/khung giờ nếu quy mô hoặc vị trí mới yêu cầu.';
        }

        if (str_contains($ascii, 'trangthaivanhanhsauthaydoi')) {
            return 'Trạng thái vận hành sau thay đổi: Chờ duyệt/phát hành phụ lục, sau đó cập nhật theo trạng thái sân con thực tế.';
        }

        if (str_contains($ascii, 'thoigiantamdungnhanlichmoi')) {
            return 'Thời gian tạm dừng nhận lịch mới nếu có: Không tạm dừng, trừ khi SportGo yêu cầu trong quá trình cập nhật.';
        }

        if (str_contains($ascii, 'bophancanhanchiu')) {
            $ownerName = $this->venueChangeOwnerDisplayName($data) ?: 'Chủ sân/đối tác';
            return "Bộ phận/cá nhân chịu trách nhiệm phối hợp: Chủ sân/đối tác: {$ownerName}; SportGo: Bộ phận tiếp nhận SportGo";
        }

        $fields = $this->venueChangeRequestTemplateBodyValues($data, $documentType);
        $lookup = [];
        foreach ($fields as $label => $value) {
            $key = Str::ascii($this->normalizeDocxLabel((string) $label));
            $lookup[$key] = [(string) $label, $this->cellPlainValue($value) ?: 'Chưa cung cấp'];
        }

        uksort($lookup, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($lookup as $key => [$label, $value]) {
            if ($key !== '' && str_starts_with($ascii, $key)) {
                $prefix = str_starts_with(ltrim($text), '-') ? '- ' : '';
                return $prefix . $label . ': ' . $value;
            }
        }

        return null;
    }

    private function venueChangeOwnerDisplayName(array $data): ?string
    {
        return $this->firstFilled($data, [
            'business_name',
            'partner_name',
            'owner_full_name',
            'owner_signer_name',
            'representative_name',
            'full_name',
        ]);
    }

    private function venueChangeLegalId(array $data): ?string
    {
        return $this->joinFilled([
            $this->firstFilled($data, ['identity_number', 'representative_identity_number']),
            $this->firstFilled($data, ['tax_code']),
            $this->firstFilled($data, ['business_license_number', 'business_code']),
        ], '; ');
    }

    private function workflowTemplateBodyValues(array $data, string $documentType): array
    {
        $fallback = 'Chưa cung cấp';
        $sportgoName = $this->firstFilled($data, ['sportgo_company_name']) ?: 'Công ty TNHH SportGo';
        $sportgoTax = $this->firstFilled($data, ['sportgo_tax_code']) ?: '0000000000';
        $sportgoAddress = $this->firstFilled($data, ['sportgo_address']) ?: 'Tòa P cao đẳng FPT Polytechnic Đường Phan Tây Nhạc, Phường Xuân Phương, Hà Nội';
        $sportgoRep = $this->firstFilled($data, ['party_a_rep', 'issuer_representative_name', 'sportgo_representative_name']) ?: 'Đại diện SportGo';
        $sportgoRole = $this->firstFilled($data, ['sportgo_representative_title']) ?: 'Đại diện được ủy quyền';

        $ownerRepresentativeName = $this->firstFilled($data, [
            'owner_signer_full_name',
            'representative_name',
            'owner_full_name',
            'venue_owner_name',
            'full_name',
        ]) ?: $fallback;
        $ownerDisplayName = $this->firstFilled($data, [
            'business_name',
            'party_b_name',
            'receiver_name',
        ]) ?: $ownerRepresentativeName;
        $ownerLegalId = $this->firstFilled($data, [
            'party_b_id',
            'identity_number',
            'tax_code',
            'business_license_number',
        ]) ?: $fallback;
        $ownerAddress = $this->firstFilled($data, ['party_b_address', 'business_address', 'applicant_address', 'venue_address']) ?: $fallback;
        $venueName = $this->firstFilled($data, ['venue_name', 'venue_cluster_list']) ?: $fallback;
        $venueCode = $this->firstFilled($data, ['venue_code', 'venue_cluster_code', 'venue_cluster_id']) ?: $fallback;
        $venueAddress = $this->firstFilled($data, ['venue_address']) ?: $fallback;
        $contractCode = $this->firstFilled($data, ['contract_code', 'contract_number']) ?: $fallback;
        $terminationCode = $this->firstFilled($data, ['termination_code', 'termination_request_code']) ?: $fallback;
        $terminationReason = $this->firstFilled($data, ['termination_reason', 'reason']) ?: $fallback;
        $effectiveDate = $this->firstFilled($data, ['effective_termination_date', 'effective_date', 'agreed_termination_date', 'requested_effective_date']) ?: $fallback;
        $settlementItems = $this->firstFilled($data, ['settlement_items', 'settlement_table']) ?: $fallback;
        $bankAccount = $this->firstFilled($data, ['bank_account', 'owner_bank_account_snapshot']) ?: $this->joinFilled([
            $this->firstFilled($data, ['bank_name']),
            $this->firstFilled($data, ['account_number']),
            $this->firstFilled($data, ['account_holder_name']),
        ], ' - ') ?: $fallback;

        $commonOwner = [
            'Họ tên/Tên tổ chức' => $ownerDisplayName,
            'Số CCCD/CMND/Hộ chiếu/MST/ĐKKD' => $ownerLegalId,
            'Người đại diện nếu là tổ chức' => $ownerDisplayName !== $ownerRepresentativeName ? $ownerRepresentativeName : 'Không áp dụng',
            'Người đại diện nếu có' => $ownerRepresentativeName,
            'Chức vụ/Quan hệ đại diện' => $this->firstFilled($data, ['representative_position']) ?: 'Chủ sân/đối tác',
            'Số điện thoại' => $this->firstFilled($data, ['owner_phone', 'phone']) ?: $fallback,
            'Email' => $this->firstFilled($data, ['owner_email', 'email']) ?: $fallback,
            'Địa chỉ liên hệ/trụ sở' => $ownerAddress,
            'Tên cụm sân' => $venueName,
            'Mã cụm sân trên hệ thống' => $venueCode,
            'Địa chỉ cụm sân' => $venueAddress,
        ];

        return match ($documentType) {
            'termination_request' => [
                ...$commonOwner,
                'Lý do chấm dứt' => $terminationReason,
                'Lý do chính' => $terminationReason,
                'Mô tả chi tiết lý do' => $this->firstFilled($data, ['detail_reason']) ?: $terminationReason,
                'Tình trạng hoạt động hiện tại' => $this->firstFilled($data, ['venue_status_label']) ?: 'Đang hoạt động',
                'Thời điểm đề nghị ngừng nhận booking mới' => $this->firstFilled($data, ['requested_stop_booking_at', 'requested_at']) ?: $fallback,
                'Thời điểm đề nghị chấm dứt hoàn toàn' => $effectiveDate,
                'Người phụ trách phối hợp trong giai đoạn chuyển tiếp' => $this->firstFilled($data, ['termination_coordinator', 'owner_full_name']) ?: $fallback,
                'Yêu cầu về thời điểm chấm dứt' => $effectiveDate,
                'Yêu cầu quyết toán' => $bankAccount,
                'Số hợp đồng hợp tác' => $contractCode,
                'Ngày ký hợp đồng' => $this->firstFilled($data, ['contract_signed_at', 'signed_date']) ?: $fallback,
                'Booking còn hiệu lực' => $this->firstFilled($data, ['booking_status_summary']) ?: $fallback,
                'Yêu cầu hoàn/hủy đang xử lý' => $this->firstFilled($data, ['refund_status_summary']) ?: $fallback,
                'Khiếu nại đang mở' => $this->firstFilled($data, ['complaint_status_summary']) ?: $fallback,
                'Yêu cầu rút tiền đang chờ' => $this->firstFilled($data, ['withdrawal_status_summary']) ?: $fallback,
                'Tài khoản nhận tiền hoàn/trả sau quyết toán' => $bankAccount,
            ],
            'mutual_liquidation_minutes' => [
                'Tên đơn vị' => $sportgoName,
                'Mã số thuế/ĐKKD' => $sportgoTax,
                'Địa chỉ' => $sportgoAddress,
                'Người đại diện' => $sportgoRep,
                'Chức vụ/Căn cứ đại diện' => $sportgoRole,
                ...$commonOwner,
                'Số hợp đồng' => $contractCode,
                'Số hợp đồng hợp tác' => $contractCode,
                'Lý do thanh lý' => $terminationReason,
                'Thời điểm chấm dứt' => $effectiveDate,
                'Tình trạng booking' => $this->firstFilled($data, ['booking_status_summary']) ?: 'Theo dữ liệu đối soát trên hệ thống',
                'Tình trạng hoàn/hủy' => $this->firstFilled($data, ['refund_status_summary']) ?: 'Theo dữ liệu đối soát trên hệ thống',
                'Bảng quyết toán' => $settlementItems,
                'Số dư ví chủ sân' => $this->firstFilled($data, ['owner_wallet_available_amount']) ?: '0 VND',
                'Phí nền tảng còn lại' => $this->firstFilled($data, ['unpaid_platform_fee_amount']) ?: '0 VND',
                'Số tiền SportGo phải trả đối tác' => $this->firstFilled($data, ['final_payable_to_owner']) ?: '0 VND',
                'Số tiền đối tác phải trả SportGo' => $this->firstFilled($data, ['final_receivable_from_owner']) ?: '0 VND',
                'Thời điểm thu hồi quyền chủ sân' => $this->firstFilled($data, ['owner_access_revocation_date']) ?: $effectiveDate,
            ],
            'unilateral_termination_notice' => [
                'Tên đối tác/chủ sân' => $ownerDisplayName,
                'Số CCCD/CMND/Hộ chiếu/MST/ĐKKD' => $ownerLegalId,
                'Người đại diện nếu có' => $ownerRepresentativeName,
                'Số hợp đồng hợp tác' => $contractCode,
                'Tên cụm sân' => $venueName,
                'Mã cụm sân trên hệ thống' => $venueCode,
                'Địa chỉ cụm sân' => $venueAddress,
                'Lý do chấm dứt' => $terminationReason,
                'Căn cứ chấm dứt' => $this->firstFilled($data, ['legal_basis_text']) ?: 'Theo hợp đồng và chính sách vận hành SportGo',
                'Thời điểm có hiệu lực dự kiến' => $effectiveDate,
                'Thời điểm hiệu lực' => $effectiveDate,
                'Thời gian chuyển tiếp' => $this->firstFilled($data, ['transition_end_at']) ?: $effectiveDate,
                'Yêu cầu đối soát/quyết toán' => $this->firstFilled($data, ['required_actions']) ?: 'Hai bên thực hiện đối soát theo dữ liệu hệ thống',
                'Thời hạn phản hồi' => $this->firstFilled($data, ['settlement_deadline']) ?: $fallback,
            ],
            'settlement_minutes' => [
                'Bên A' => $sportgoName,
                'Đại diện Bên A' => $sportgoRep,
                'Chức vụ/Căn cứ đại diện' => $sportgoRole,
                'Bên B' => $ownerDisplayName,
                'Đại diện Bên B' => $ownerRepresentativeName,
                'Cụm sân liên quan' => $venueName,
                'Số hợp đồng' => $contractCode,
                'Kỳ/Thời điểm quyết toán' => $this->firstFilled($data, ['calculation_date', 'settlement_date']) ?: now()->format('d/m/Y'),
                'Booking còn hiệu lực' => $this->firstFilled($data, ['booking_status_summary']) ?: 'Không có booking còn hiệu lực.',
                'Booking đã hoàn thành nhưng chưa đối soát' => $this->firstFilled($data, ['completed_booking_reconciliation_summary']) ?: 'Không còn booking chờ đối soát.',
                'Yêu cầu hoàn/hủy đang xử lý' => $this->firstFilled($data, ['refund_status_summary']) ?: 'Không có yêu cầu hoàn/hủy đang xử lý.',
                'Khiếu nại đang mở' => $this->firstFilled($data, ['complaint_status_summary']) ?: 'Không có khiếu nại đang mở.',
                'Yêu cầu rút tiền đang chờ' => $this->firstFilled($data, ['withdrawal_status_summary']) ?: 'Không có yêu cầu rút tiền đang chờ.',
                'Số dư ví chủ sân còn được rút' => $this->firstFilled($data, ['owner_wallet_available_amount']) ?: '0 VND',
                'Tiền đang chờ rút/đối soát' => $this->firstFilled($data, ['pending_withdrawal_amount', 'withdrawal_code', 'withdrawal_status']) ?: '0 VND',
                'Phí nền tảng/kỳ phí còn lại' => $this->firstFilled($data, ['platform_fee_remaining_refund_amount']) ?: '0 VND',
                'Phí nền tảng còn lại được hoàn nếu có' => $this->firstFilled($data, ['platform_fee_remaining_refund_amount']) ?: '0 VND',
                'Khoản phải thu từ đối tác' => $this->firstFilled($data, ['final_receivable_from_owner', 'unpaid_platform_fee_amount']) ?: '0 VND',
                'Khoản phải thu từ đối tác nếu có' => $this->firstFilled($data, ['final_receivable_from_owner', 'unpaid_platform_fee_amount']) ?: '0 VND',
                'Khoản điều chỉnh' => $this->firstFilled($data, ['adjustment_amount']) ?: '0 VND',
                'Khoản điều chỉnh do hoàn/hủy booking' => $this->firstFilled($data, ['adjustment_amount']) ?: '0 VND',
                'Voucher/khuyến mại phải điều chỉnh nếu có' => $this->firstFilled($data, ['voucher_adjustment_amount']) ?: '0 VND',
                'Khoản bồi hoàn/bồi thường nếu có' => $this->firstFilled($data, ['compensation_amount']) ?: '0 VND',
                'Số tiền cuối cùng' => $this->joinFilled([
                    'SportGo trả đối tác: ' . ($this->firstFilled($data, ['final_payable_to_owner']) ?: '0 VND'),
                    'Đối tác trả SportGo: ' . ($this->firstFilled($data, ['final_receivable_from_owner']) ?: '0 VND'),
                ], '; '),
                'Bằng chữ' => $this->firstFilled($data, ['settlement_amount_in_words']) ?: 'Không đồng',
                'Bên có nghĩa vụ thanh toán' => $this->firstFilled($data, ['settlement_obligor']) ?: 'Không phát sinh',
                'Bên nhận tiền' => $this->firstFilled($data, ['settlement_receiver']) ?: 'Không phát sinh',
                'Phương thức thanh toán/hoàn trả' => $this->firstFilled($data, ['settlement_payment_method']) ?: 'Không phát sinh',
                'Tài khoản nhận tiền' => $bankAccount,
                'Thời hạn thực hiện' => $this->firstFilled($data, ['settlement_deadline']) ?: $effectiveDate,
                'Bảng quyết toán' => $settlementItems,
            ],
            default => [],
        };
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
            'Tên đơn vị' => $this->firstFilled($data, ['sportgo_company_name']) ?: 'Công ty TNHH SportGo',
            'Mã số thuế/ĐKKD' => $this->firstFilled($data, ['sportgo_tax_code']) ?: '0000000000',
            'Địa chỉ trụ sở' => $this->firstFilled($data, ['sportgo_address']) ?: config('app.url'),
            'Người đại diện' => $this->firstFilled($data, ['sportgo_representative_name', 'sportgo_representative']) ?: 'Đại diện SportGo',
            'Chức vụ' => $this->firstFilled($data, ['sportgo_representative_title', 'sportgo_representative_position']) ?: 'Đại diện pháp lý',
            'Căn cứ đại diện/ủy quyền' => $this->firstFilled($data, ['sportgo_authorization_basis']) ?: 'Người đại diện theo pháp luật',
            'Số điện thoại/Email' => $this->joinFilled([
                $this->firstFilled($data, ['sportgo_phone']),
                $this->firstFilled($data, ['sportgo_email']),
            ], ' - ') ?: 'Chưa cung cấp',
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

    private function isInsideDocxTable(\DOMNode $node): bool
    {
        $current = $node->parentNode;
        while ($current) {
            if ($current instanceof \DOMElement && $current->localName === 'tbl') {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
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

    private function polishSignedDocumentFile(GeneratedDocument $document): void
    {
        if (! in_array($document->document_type, ['partner_application_form', 'partner_contract'], true)) {
            return;
        }

        $path = $document->generated_file_path;
        if (! $path || ! Storage::disk('local')->exists($path) || ! class_exists(ZipArchive::class) || ! class_exists(\DOMDocument::class)) {
            return;
        }

        $zip = new ZipArchive();
        $filePath = Storage::disk('local')->path($path);
        if ($zip->open($filePath) !== true) {
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
        $document->loadMissing('signatures');

        $sportgoSignature = $document->signatures
            ->where('signer_side', 'sportgo')
            ->where('status', 'signed')
            ->sortByDesc('signed_at')
            ->first();
        $ownerSignature = $document->signatures
            ->where('signer_side', 'owner')
            ->where('status', 'signed')
            ->sortByDesc('signed_at')
            ->first();
        $partnerApplication = $document->partner_application_id
            ? PartnerApplication::with('user')->find($document->partner_application_id)
            : null;

        if ($document->document_type === 'partner_contract') {
            if ($sportgoSignature?->signed_at) {
                $effectiveDate = $sportgoSignature->signed_at->format('d/m/Y');
                foreach ($xpath->query('//w:p') as $paragraph) {
                    $ascii = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($paragraph, $xpath)));
                    if (str_contains($ascii, 'hopdongcohieulucke')) {
                        $changed = $this->replaceDocxCellText($paragraph, $xpath, "• Hợp đồng có hiệu lực kể từ thời điểm Các Bên ký/xác nhận đầy đủ hoặc từ ngày {$effectiveDate} theo thỏa thuận.") || $changed;
                        break;
                    }
                }
            }
        }

        $tables = $xpath->query('//w:tbl');
        $signatureTable = $tables->item(max(0, $tables->length - 1));
        if ($signatureTable) {
            $changed = $this->ensureDocxTableBorders($signatureTable) || $changed;
            $changed = $this->centerDocxTableParagraphs($signatureTable, $xpath) || $changed;

            $rows = $xpath->query('./w:tr', $signatureTable);
            if (in_array($document->document_type, ['partner_contract', 'venue_scale_appendix', 'venue_location_appendix'], true)) {
                $nameRow = $rows->item(3);
                if ($nameRow) {
                    $cells = $xpath->query('./w:tc', $nameRow);
                    if ($cells->length >= 2) {
                        $sportgoName = trim((string) ($sportgoSignature?->signer_full_name ?: config('sportgo.contracts.sportgo_representative', 'Đại diện SportGo')));
                        $ownerName = trim((string) ($ownerSignature?->signer_full_name ?: $partnerApplication?->representative_name ?: $partnerApplication?->user?->name));
                        $changed = $this->replaceDocxCellText($cells->item(0), $xpath, $sportgoName) || $changed;
                        if ($ownerName !== '') {
                            $changed = $this->replaceDocxCellText($cells->item(1), $xpath, $ownerName) || $changed;
                        }
                    }
                }
            }

            if ($document->document_type === 'partner_application_form') {
                $ownerName = trim((string) ($ownerSignature?->signer_full_name ?: $partnerApplication?->representative_name ?: $partnerApplication?->user?->name));
                if ($ownerName !== '') {
                    foreach ($xpath->query('.//w:p', $signatureTable) as $paragraph) {
                        $ascii = Str::ascii($this->normalizeDocxLabel($this->docxNodeText($paragraph, $xpath)));
                        if ($ascii === 'hovaten' || str_starts_with($ascii, 'hovaten')) {
                            $changed = $this->replaceDocxCellText($paragraph, $xpath, $ownerName) || $changed;
                            break;
                        }
                    }
                }
            }
        }

        if ($changed) {
            $zip->addFromString($entry, $dom->saveXML());
        }

        $zip->close();
    }

    private function ensureDocxTableBorders(\DOMNode $table): bool
    {
        $document = $table->ownerDocument;
        if (! $document) {
            return false;
        }

        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $tblPr = null;
        foreach ($table->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tblPr') {
                $tblPr = $child;
                break;
            }
        }

        if (! $tblPr) {
            $tblPr = $document->createElementNS($namespace, 'w:tblPr');
            $table->insertBefore($tblPr, $table->firstChild);
        }

        foreach (iterator_to_array($tblPr->childNodes) as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tblBorders') {
                $tblPr->removeChild($child);
            }
        }

        $borders = $document->createElementNS($namespace, 'w:tblBorders');
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $border = $document->createElementNS($namespace, 'w:' . $edge);
            $border->setAttributeNS($namespace, 'w:val', 'single');
            $border->setAttributeNS($namespace, 'w:sz', '4');
            $border->setAttributeNS($namespace, 'w:space', '0');
            $border->setAttributeNS($namespace, 'w:color', '000000');
            $borders->appendChild($border);
        }
        $tblPr->appendChild($borders);

        return true;
    }

    private function centerDocxTableParagraphs(\DOMNode $table, \DOMXPath $xpath): bool
    {
        $document = $table->ownerDocument;
        if (! $document) {
            return false;
        }

        $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $changed = false;
        foreach ($xpath->query('.//w:p', $table) as $paragraph) {
            $pPr = null;
            foreach ($paragraph->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'pPr') {
                    $pPr = $child;
                    break;
                }
            }

            if (! $pPr) {
                $pPr = $document->createElementNS($namespace, 'w:pPr');
                $paragraph->insertBefore($pPr, $paragraph->firstChild);
            }

            foreach (iterator_to_array($pPr->childNodes) as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'jc') {
                    $pPr->removeChild($child);
                }
            }

            $jc = $document->createElementNS($namespace, 'w:jc');
            $jc->setAttributeNS($namespace, 'w:val', 'center');
            $pPr->appendChild($jc);
            $changed = true;
        }

        return $changed;
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

    private function docxTwoPartySignatureTableXml(): string
    {
        return '<w:tbl>'
            . '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:left w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="auto"/>'
            . '</w:tblBorders></w:tblPr>'
            . '<w:tr><w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>ĐẠI DIỆN BÊN A - SPORTGO</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>ĐẠI DIỆN BÊN B - ĐỐI TÁC/CHỦ SÂN</w:t></w:r></w:p></w:tc></w:tr>'
            . '<w:tr><w:tc><w:p><w:r><w:t>Ký, ghi rõ họ tên</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>Ký, ghi rõ họ tên</w:t></w:r></w:p></w:tc></w:tr>'
            . '<w:tr><w:tc><w:p><w:r><w:t>{{signature_sportgo}}</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>{{signature_owner}}</w:t></w:r></w:p></w:tc></w:tr>'
            . '<w:tr><w:tc><w:p><w:r><w:t>{{sportgo_signer_name}}</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>{{owner_signer_name}}</w:t></w:r></w:p></w:tc></w:tr>'
            . '</w:tbl>';
    }

    private function storeSignatureImage(GeneratedDocumentSignature $signature, string $signatureImage): Media
    {
        $binary = $this->decodeSignatureImage($signatureImage);
        $filePath = 'partner-signatures/' . now()->format('Y/m') . '/' . Str::random(40) . '.png';

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

    private function withAutomaticAppendixIdentity(
        string $documentType,
        array $renderData,
        array $context,
        string $referenceType,
        string $referenceId
    ): array {
        if (! in_array($documentType, ['venue_scale_appendix', 'venue_location_appendix'], true)) {
            return $renderData;
        }

        $scope = GeneratedDocument::query()
            ->whereIn('document_type', ['venue_scale_appendix', 'venue_location_appendix']);
        if (! empty($context['partner_contract_id'])) {
            $scope->where('partner_contract_id', $context['partner_contract_id']);
        } elseif (! empty($context['venue_cluster_id'])) {
            $scope->where('venue_cluster_id', $context['venue_cluster_id']);
        } elseif (! empty($context['partner_application_id'])) {
            $scope->where('partner_application_id', $context['partner_application_id']);
        } else {
            $scope->where('entity_type', $context['entity_type'] ?? null)
                ->where('entity_id', $context['entity_id'] ?? null);
        }

        $sameReference = (clone $scope)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->latest('document_version')
            ->latest('generated_at')
            ->first(['render_data']);
        $sequence = $this->appendixSequenceFromRenderData($sameReference?->render_data);

        if ($sequence < 1) {
            $highestSequence = (clone $scope)
                ->where('status', '!=', 'draft_preview')
                ->get(['render_data'])
                ->map(fn (GeneratedDocument $document): int => $this->appendixSequenceFromRenderData($document->render_data))
                ->max();
            $sequence = max(0, (int) $highestSequence) + 1;
        }

        $sequence = max(1, (int) $sequence);
        $renderData['appendix_sequence'] = $sequence;
        $renderData['appendix_number'] = $this->toRomanNumeral($sequence);

        return $renderData;
    }

    private function appendixSequenceFromRenderData(?array $renderData): int
    {
        if (! is_array($renderData)) {
            return 0;
        }
        $sequence = (int) ($renderData['appendix_sequence'] ?? 0);
        if ($sequence > 0) {
            return $sequence;
        }

        return $this->romanNumeralValue((string) ($renderData['appendix_number'] ?? ''));
    }

    private function romanNumeralValue(string $roman): int
    {
        $roman = strtoupper(trim($roman));
        if ($roman === '' || ! preg_match('/^[IVXLCDM]+$/', $roman)) {
            return 0;
        }

        $values = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50, 'C' => 100, 'D' => 500, 'M' => 1000];
        $total = 0;
        $previous = 0;
        for ($index = strlen($roman) - 1; $index >= 0; $index--) {
            $value = $values[$roman[$index]];
            $total += $value < $previous ? -$value : $value;
            $previous = max($previous, $value);
        }

        return $total;
    }

    private function toRomanNumeral(int $number): string
    {
        $number = max(1, min(3999, $number));
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $result = '';
        foreach ($map as $value => $roman) {
            while ($number >= $value) {
                $result .= $roman;
                $number -= $value;
            }
        }

        return $result;
    }

    private function appendixDocumentTitle(string $title, string $appendixNumber): string
    {
        $baseTitle = trim(preg_replace('/^Phụ lục(?:\s+[IVXLCDM]+)?\s*(?:[-:]\s*)?/iu', '', $title) ?? $title);

        return 'Phụ lục ' . $appendixNumber . ($baseTitle !== '' ? ' - ' . $baseTitle : '');
    }

    private function defaultTitle(string $documentType, array $renderData): string
    {
        return match ($documentType) {
            'venue_scale_request' => 'Đơn yêu cầu mở rộng quy mô sân ' . ($renderData['venue_name'] ?? $renderData['cluster_name'] ?? ''),
            'venue_location_change_request' => 'Đơn yêu cầu thay đổi vị trí cụm sân ' . ($renderData['venue_name'] ?? $renderData['cluster_name'] ?? ''),
            'venue_scale_appendix' => 'Phụ lục ' . ($renderData['appendix_number'] ?? 'I') . ' - thay đổi quy mô sân ' . ($renderData['venue_name'] ?? $renderData['cluster_name'] ?? ''),
            'venue_location_appendix' => 'Phụ lục ' . ($renderData['appendix_number'] ?? 'I') . ' - thay đổi vị trí cụm sân ' . ($renderData['venue_name'] ?? $renderData['cluster_name'] ?? ''),
            'partner_application_form' => 'Đơn đăng ký đối tác ' . ($renderData['venue_name'] ?? ''),
            'partner_contract' => 'Hợp đồng hợp tác ' . ($renderData['venue_name'] ?? ''),
            'termination_request' => 'Đơn yêu cầu chấm dứt hợp tác',
            'termination_cancellation_request' => 'Đơn xác nhận hủy yêu cầu chấm dứt hợp tác',
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
