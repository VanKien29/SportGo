<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Mail\Partner\VenueScaleRequestReceivedMail;
use App\Models\CourtType;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\VenueCourtApprovalRequest;
use App\Services\Partner\PartnerDocumentService;
use App\Services\Partner\PartnerProfileDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VenueCourtApprovalController extends Controller
{
    public function __construct(
        private readonly PartnerProfileDocumentService $profileDocuments,
        private readonly PartnerDocumentService $documents,
    )
    {
    }

    /**
     * Lấy danh sách yêu cầu quy mô sân của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem yêu cầu của cụm sân này.'], 403);
        }

        $query = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->with(['courtType:id,name', 'requestedBy:id,full_name,username,email,phone', 'reviewedBy:id,full_name,username', 'generatedDocument.signatures'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get()->map(fn ($r) => $this->payload($r));

        return response()->json(['data' => $requests]);
    }

    /**
     * Gửi yêu cầu mở rộng quy mô (thêm sân con mới).
     */
    public function preview(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền tạo bản xem trước cho cụm sân này.'], 403);
        }

        $hasOpenRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('status', ['pending_owner_signature', 'pending', 'approved_pending_appendix', 'need_supplement'])
            ->exists();

        if ($hasOpenRequest) {
            return response()->json([
                'message' => 'Cum san dang co yeu cau thay doi quy mo chua hoan tat. Vui long hoan tat, bo sung hoac huy yeu cau hien tai truoc khi tao yeu cau moi.',
            ], 422);
        }

        $data = $request->validate([
            'change_type'    => ['nullable', Rule::in(['add', 'remove', 'mixed'])],
            'court_type_id'  => ['nullable', 'integer', 'exists:court_types,id'],
            'name'           => ['nullable', 'string', 'max:100'],
            'requested_courts' => ['nullable', 'array', 'max:20'],
            'requested_courts.*.court_type_id' => ['required_with:requested_courts', 'integer', 'exists:court_types,id'],
            'requested_courts.*.name' => ['required_with:requested_courts', 'string', 'max:100'],
            'removed_court_ids' => ['nullable', 'array', 'max:20'],
            'removed_court_ids.*' => ['uuid', 'exists:venue_courts,id'],
            'note'           => ['nullable', 'string', 'max:1000'],
            'evidence_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
            'preview_document_id' => ['nullable', 'uuid'],
        ]);
        $data = $this->normalizeScaleRequestData($data, $cluster);

        $courtType = CourtType::query()->findOrFail($data['court_type_id']);
        $previewRequest = new VenueCourtApprovalRequest();
        $previewRequest->forceFill([
            'id' => (string) Str::uuid(),
            'venue_cluster_id' => $cluster->id,
            'court_type_id' => $courtType->id,
            'name' => $data['name'],
            'change_type' => $data['change_type'],
            'requested_courts' => $data['requested_courts'],
            'removed_court_ids' => $data['removed_court_ids'],
            'status' => 'draft_preview',
            'requested_by' => $request->user()->id,
            'status_reason' => $data['note'] ?? null,
            'created_at' => now(),
        ]);
        $previewRequest->setRelation('courtType', $courtType);
        $previewRequest->setRelation('requestedBy', $request->user());
        $previewRequest->setRelation('venueCluster', $cluster);

        $renderData = array_merge($this->scaleRequestRenderData($cluster, $previewRequest), [
            'attachment_list' => $this->uploadedFileNames($request->file('supplementary_documents', [])),
            'evidence_file_name' => $request->file('evidence_image')?->getClientOriginalName(),
        ]);

        $this->deleteDraftPreviewDocuments('venue_scale_request', $cluster);

        $document = $this->documents->generateDocument('venue_scale_request', $cluster, $renderData, $request->user(), [
            'reference_type' => 'venue_scale_request_preview',
            'reference_id' => (string) Str::uuid(),
            'owner_id' => $cluster->owner_id,
            'venue_cluster_id' => $cluster->id,
            'entity_type' => VenueCluster::class,
            'entity_id' => $cluster->id,
            'status' => 'draft_preview',
            'title' => 'Bản xem trước đơn yêu cầu mở rộng quy mô sân ' . $cluster->name,
        ]);

        return response()->json([
            'message' => 'Đã tạo bản xem trước đơn yêu cầu mở rộng quy mô.',
            'data' => $this->documentPayload($document),
        ]);
    }

    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền gửi yêu cầu cho cụm sân này.'], 403);
        }

        if ($cluster->status === 'locked') {
            return response()->json(['message' => 'Cụm sân đang bị khóa. Không thể gửi yêu cầu mới.'], 422);
        }

        $hasOpenRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('status', ['pending_owner_signature', 'pending', 'approved_pending_appendix', 'need_supplement'])
            ->exists();

        if ($hasOpenRequest) {
            return response()->json([
                'message' => 'Cum san dang co yeu cau thay doi quy mo chua hoan tat. Vui long hoan tat, bo sung hoac huy yeu cau hien tai truoc khi tao yeu cau moi.',
            ], 422);
        }

        $data = $request->validate([
            'change_type'    => ['nullable', Rule::in(['add', 'remove', 'mixed'])],
            'court_type_id'  => ['nullable', 'integer', 'exists:court_types,id'],
            'name'           => ['nullable', 'string', 'max:100'],
            'requested_courts' => ['nullable', 'array', 'max:20'],
            'requested_courts.*.court_type_id' => ['required_with:requested_courts', 'integer', 'exists:court_types,id'],
            'requested_courts.*.name' => ['required_with:requested_courts', 'string', 'max:100'],
            'removed_court_ids' => ['nullable', 'array', 'max:20'],
            'removed_court_ids.*' => ['uuid', 'exists:venue_courts,id'],
            'note'           => ['nullable', 'string', 'max:1000'],
            'evidence_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
            'preview_document_id' => ['nullable', 'uuid'],
        ], [
            'court_type_id.required'  => 'Vui lòng chọn loại sân.',
            'court_type_id.exists'    => 'Loại sân không tồn tại.',
            'name.required'           => 'Vui lòng nhập tên sân.',
            'name.max'                => 'Tên sân không được quá 100 ký tự.',
            'evidence_image.required' => 'Vui lòng tải lên hình ảnh minh chứng quy mô sân.',
            'evidence_image.image'    => 'File minh chứng phải là ảnh.',
            'evidence_image.mimes'    => 'Ảnh minh chứng phải có định dạng: jpg, jpeg, png, webp.',
            'evidence_image.max'      => 'Ảnh minh chứng không được quá 5MB.',
            'supplementary_documents.required' => 'Vui lòng tải lên giấy ĐKKD hoặc giấy cập nhật kinh doanh liên quan đến yêu cầu mở rộng quy mô.',
            'supplementary_documents.*.mimes' => 'Giấy tờ bổ sung phải có định dạng: jpg, jpeg, png, webp, pdf, doc, docx.',
            'supplementary_documents.*.max' => 'Mỗi giấy tờ bổ sung không được quá 10MB.',
            'signature_image.required' => 'Vui lòng ký xác nhận yêu cầu trước khi gửi.',
        ]);

        // Xử lý upload ảnh minh chứng
        $data = $this->normalizeScaleRequestData($data, $cluster);

        $evidencePath = null;
        if ($request->hasFile('evidence_image')) {
            $evidencePath = $request->file('evidence_image')
                ->store('approval-evidence/' . $clusterId, 'public');
        }

        $approvalRequest = VenueCourtApprovalRequest::create([
            'venue_cluster_id' => $clusterId,
            'court_type_id'    => $data['court_type_id'],
            'name'             => $data['name'],
            'change_type'      => $data['change_type'],
            'requested_courts' => $data['requested_courts'],
            'removed_court_ids' => $data['removed_court_ids'],
            'status'           => 'pending_owner_signature',
            'requested_by'     => $request->user()->id,
            'status_reason'    => $data['note'] ?? null,
            'evidence_image'   => $evidencePath,
        ]);
        $documents = $this->profileDocuments->attachVenueRequestDocuments(
            $cluster,
            $this->filesArray($request->file('supplementary_documents', [])),
            $approvalRequest->id,
            'scale_request_supplement',
            'scale_request_documents',
            'Giấy tờ bổ sung yêu cầu mở rộng quy mô',
            'Giấy tờ chủ sân gửi kèm yêu cầu mở rộng quy mô sân.'
        );
        if ($documents !== []) {
            $approvalRequest->forceFill(['supplementary_documents' => $documents])->save();
        }

        $this->deleteDraftPreviewDocuments('venue_scale_request', $cluster);
        $this->generateScaleDocument($cluster, $approvalRequest, $request, $data['preview_document_id'] ?? null);

        $approvalRequest->load(['courtType:id,name']);

        return response()->json([
            'message' => 'Gửi yêu cầu thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => [
                ...$this->payload($approvalRequest->load(['courtType:id,name', 'requestedBy:id,full_name,username,email,phone', 'generatedDocument.signatures'])),
                'partner_application_id' => $this->partnerApplication($cluster)?->id,
            ],
        ], 201);
    }

    /**
     * Hủy yêu cầu đang ở trạng thái chờ duyệt.
     */
    public function supplement(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền bổ sung yêu cầu này.'], 403);
        }

        $approvalRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($approvalRequest->status !== 'need_supplement') {
            return response()->json(['message' => 'Chỉ có yêu cầu đang cần bổ sung mới được nộp thêm giấy tờ.'], 422);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
            'preview_document_id' => ['nullable', 'uuid'],
        ], [
            'supplementary_documents.required' => 'Vui lòng tải lên ít nhất một giấy tờ bổ sung.',
            'supplementary_documents.*.mimes' => 'Giấy tờ bổ sung phải có định dạng: jpg, jpeg, png, webp, pdf, doc, docx.',
            'supplementary_documents.*.max' => 'Mỗi giấy tờ bổ sung không được quá 10MB.',
            'signature_image.required' => 'Vui lòng ký xác nhận yêu cầu trước khi gửi.',
        ]);

        $documents = $this->profileDocuments->attachVenueRequestDocuments(
            $cluster,
            $this->filesArray($request->file('supplementary_documents', [])),
            $approvalRequest->id,
            'scale_request_supplement',
            'scale_request_documents',
            'Giấy tờ bổ sung yêu cầu mở rộng quy mô',
            'Giấy tờ chủ sân bổ sung theo yêu cầu của SportGo.'
        );

        $approvalRequest->forceFill([
            'status' => 'pending_owner_signature',
            'status_reason' => $data['note'] ?? 'Chủ sân đã bổ sung giấy tờ theo yêu cầu.',
            'supplementary_documents' => array_values(array_merge($approvalRequest->supplementary_documents ?: [], $documents)),
            'signature_image' => null,
            'signature_hash' => null,
            'signed_at' => null,
        ])->save();

        $this->generateScaleDocument($cluster, $approvalRequest->refresh(), $request, $data['preview_document_id'] ?? null);

        return response()->json([
            'message' => 'Đã nộp giấy tờ bổ sung. Yêu cầu được chuyển lại về trạng thái chờ duyệt.',
            'data' => [
                ...$this->payload($approvalRequest->fresh(['courtType:id,name', 'requestedBy:id,full_name,username,email,phone', 'reviewedBy:id,full_name,username', 'generatedDocument.signatures'])),
                'partner_application_id' => $this->partnerApplication($cluster)?->id,
            ],
        ]);
    }

    public function cancel(Request $request, string $clusterId, string $requestId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền hủy yêu cầu này.'], 403);
        }

        $approvalRequest = VenueCourtApprovalRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if (! in_array($approvalRequest->status, ['pending_owner_signature', 'pending'], true)) {
            return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang ở trạng thái chờ duyệt.'], 422);
        }

        $approvalRequest->forceFill([
            'status' => 'cancelled',
        ])->save();

        return response()->json([
            'message' => 'Đã hủy yêu cầu.',
            'data'    => $this->payload($approvalRequest->fresh(['courtType', 'requestedBy'])),
        ]);
    }

    private function sendOwnerMail(VenueCluster $cluster, Mailable $mail, ?string $referenceId = null): void
    {
        $owner = $cluster->owner()->first();
        if (! $owner?->email) {
            Log::warning('Venue scale request mail skipped: owner has no email.', [
                'venue_cluster_id' => $cluster->id,
                'reference_id' => $referenceId,
            ]);
            return;
        }

        try {
            Mail::to($owner->email)->send($mail);
        } catch (\Throwable $exception) {
            Log::error('Venue scale request mail failed.', [
                'venue_cluster_id' => $cluster->id,
                'reference_id' => $referenceId,
                'owner_id' => $owner->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function payload(VenueCourtApprovalRequest $r): array
    {
        return [
            'id'                      => $r->id,
            'name'                    => $r->name,
            'change_type'             => $r->change_type ?: 'add',
            'requested_courts'         => $r->requested_courts ?: [],
            'removed_court_ids'        => $r->removed_court_ids ?: [],
            'status'                  => $r->status,
            'status_reason'           => $r->status_reason,
            'evidence_image'          => $r->evidence_image,
            'evidence_image_url'      => $r->evidence_image ? asset('storage/' . $r->evidence_image) : null,
            'supplementary_documents' => $r->supplementary_documents ?: [],
            'signature_image'         => $r->signature_image,
            'signature_image_url'     => $r->signature_image ? asset('storage/' . $r->signature_image) : null,
            'signature_hash'          => $r->signature_hash,
            'signed_at'               => $r->signed_at,
            'generated_document'       => $this->documentPayload($r->generatedDocument),
            'partner_application_id'   => $this->partnerApplicationIdForCluster($r->venue_cluster_id),
            'court_type'              => $r->courtType ? ['id' => $r->courtType->id, 'name' => $r->courtType->name] : null,
            'requested_by'            => $r->requestedBy ? ['id' => $r->requestedBy->id, 'full_name' => $r->requestedBy->full_name] : null,
            'reviewed_by'             => $r->reviewedBy ? ['id' => $r->reviewedBy->id, 'full_name' => $r->reviewedBy->full_name] : null,
            'approved_venue_court_id' => $r->approved_venue_court_id,
            'reviewed_at'             => $r->reviewed_at,
            'created_at'              => $r->created_at,
        ];
    }

    private function storeSignatureImage(string $dataUrl, string $folder, string $requestId): array
    {
        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
            throw ValidationException::withMessages([
                'signature_image' => 'Chữ ký không đúng định dạng. Vui lòng ký lại.',
            ]);
        }

        $payload = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $dataUrl);
        $binary = base64_decode(str_replace(' ', '+', $payload), true);

        if ($binary === false || strlen($binary) < 100) {
            throw ValidationException::withMessages([
                'signature_image' => 'Chữ ký chưa hợp lệ. Vui lòng ký lại.',
            ]);
        }

        $hash = hash('sha256', $binary);
        $path = trim($folder, '/') . '/' . $requestId . '-' . $hash . '.png';
        Storage::disk('public')->put($path, $binary);

        return ['path' => $path, 'hash' => $hash];
    }

    private function normalizeScaleRequestData(array $data, VenueCluster $cluster): array
    {
        $changeType = $data['change_type'] ?? 'add';

        $requestedCourts = collect($data['requested_courts'] ?? [])
            ->map(fn ($court): array => [
                'court_type_id' => (int) ($court['court_type_id'] ?? 0),
                'name' => trim((string) ($court['name'] ?? '')),
            ])
            ->filter(fn (array $court): bool => $court['court_type_id'] > 0 && $court['name'] !== '')
            ->values()
            ->all();

        if ($requestedCourts === [] && ! empty($data['court_type_id']) && ! empty($data['name'])) {
            $requestedCourts[] = [
                'court_type_id' => (int) $data['court_type_id'],
                'name' => trim((string) $data['name']),
            ];
        }

        $removedCourtIds = collect($data['removed_court_ids'] ?? [])
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        if (in_array($changeType, ['add', 'mixed'], true) && $requestedCourts === []) {
            throw ValidationException::withMessages([
                'requested_courts' => 'Vui long nhap it nhat mot san can them.',
            ]);
        }

        if (in_array($changeType, ['remove', 'mixed'], true) && $removedCourtIds === []) {
            throw ValidationException::withMessages([
                'removed_court_ids' => 'Vui long chon it nhat mot san can xoa bot.',
            ]);
        }

        $firstRemovedCourt = null;
        if ($removedCourtIds !== []) {
            $validCount = VenueCourt::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('id', $removedCourtIds)
                ->count();

            if ($validCount !== count($removedCourtIds)) {
                throw ValidationException::withMessages([
                    'removed_court_ids' => 'Danh sach san can xoa khong thuoc cum san dang chon.',
                ]);
            }

            $firstRemovedCourt = VenueCourt::query()
                ->where('venue_cluster_id', $cluster->id)
                ->whereIn('id', $removedCourtIds)
                ->oldest('sort_order')
                ->oldest('created_at')
                ->first(['court_type_id', 'name']);
        }

        $firstCourt = $requestedCourts[0] ?? null;

        return array_merge($data, [
            'change_type' => $changeType,
            'requested_courts' => $requestedCourts,
            'removed_court_ids' => $removedCourtIds,
            'court_type_id' => $firstCourt['court_type_id'] ?? $firstRemovedCourt?->court_type_id ?? ($data['court_type_id'] ?? null),
            'name' => $firstCourt['name'] ?? ($data['name'] ?? $this->scaleChangeTitle($changeType, $requestedCourts, $removedCourtIds)),
        ]);
    }

    private function scaleChangeTitle(string $changeType, array $requestedCourts, array $removedCourtIds): string
    {
        return match ($changeType) {
            'remove' => 'Giam quy mo ' . count($removedCourtIds) . ' san',
            'mixed' => 'Dieu chinh quy mo san',
            default => collect($requestedCourts)->pluck('name')->filter()->implode(', ') ?: 'Bo sung san con',
        };
    }

    private function filesArray(mixed $files): array
    {
        return collect(\Illuminate\Support\Arr::wrap($files))
            ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile)
            ->values()
            ->all();
    }

    private function uploadedFileNames(mixed $files): string
    {
        return collect(\Illuminate\Support\Arr::wrap($files))
            ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile)
            ->map(fn ($file) => $file->getClientOriginalName())
            ->values()
            ->implode('; ');
    }

    private function generateScaleDocument(VenueCluster $cluster, VenueCourtApprovalRequest $approvalRequest, Request $request, ?string $previewDocumentId = null): void
    {
        $approvalRequest->loadMissing(['courtType', 'requestedBy', 'venueCluster.owner']);
        $renderData = $this->scaleRequestRenderData($cluster, $approvalRequest);
        $document = $previewDocumentId
            ? GeneratedDocument::query()
                ->whereKey($previewDocumentId)
                ->where('document_type', 'venue_scale_request')
                ->where('owner_id', $cluster->owner_id)
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', 'draft_preview')
                ->first()
            : null;

        if ($document) {
            $document->forceFill([
                'reference_type' => VenueCourtApprovalRequest::class,
                'reference_id' => $approvalRequest->id,
                'partner_application_id' => $this->partnerApplication($cluster)?->id,
                'entity_type' => VenueCluster::class,
                'entity_id' => $cluster->id,
                'status' => 'pending_owner_signature',
                'render_data' => array_merge($document->render_data ?: [], $renderData),
                'title' => 'Đơn yêu cầu mở rộng quy mô sân ' . $cluster->name,
            ])->save();
        } else {
            $document = $this->documents->generateDocument('venue_scale_request', $approvalRequest, $renderData, $request->user(), [
                'owner_id' => $cluster->owner_id,
                'venue_cluster_id' => $cluster->id,
                'partner_application_id' => $this->partnerApplication($cluster)?->id,
                'entity_type' => VenueCluster::class,
                'entity_id' => $cluster->id,
                'status' => 'pending_owner_signature',
                'title' => 'Đơn yêu cầu mở rộng quy mô sân ' . $cluster->name,
            ]);
        }

        $approvalRequest->forceFill(['generated_document_id' => $document->id])->save();
    }

    private function scaleRequestRenderData(VenueCluster $cluster, VenueCourtApprovalRequest $approvalRequest): array
    {
        $owner = $cluster->owner()->first();
        $application = $this->partnerApplication($cluster);
        $contract = $cluster->partnerContracts()->latest('created_at')->first();
        $requestedCourts = collect($approvalRequest->requested_courts ?: [])
            ->map(fn (array $court): array => [
                'name' => $court['name'] ?? null,
                'court_type_id' => (int) ($court['court_type_id'] ?? 0),
            ])
            ->filter(fn (array $court): bool => ! empty($court['name']))
            ->values();
        $typeNames = CourtType::query()
            ->whereIn('id', $requestedCourts->pluck('court_type_id')->filter()->unique())
            ->pluck('name', 'id');
        $newCourtsSummary = $requestedCourts
            ->map(fn (array $court): string => trim(($court['name'] ?? '') . ' - ' . ($typeNames[$court['court_type_id']] ?? '')))
            ->filter()
            ->implode('; ');
        $removedCourts = VenueCourt::query()
            ->with('courtType:id,name')
            ->whereIn('id', $approvalRequest->removed_court_ids ?: [])
            ->get();
        $removedCourtsSummary = $removedCourts
            ->map(fn (VenueCourt $court): string => trim($court->name . ' - ' . ($court->courtType?->name ?? '')))
            ->implode('; ');
        $changeType = $approvalRequest->change_type ?: 'add';
        $ownerSigner = $application?->representative_name ?: ($owner?->full_name ?: $owner?->username ?: 'Chủ sân');
        $expectedEffectiveDate = optional($approvalRequest->created_at)->format('d/m/Y');
        $currentCourts = $cluster->venueCourts()
            ->with('courtType:id,name')
            ->oldest('sort_order')
            ->oldest('created_at')
            ->get(['id', 'venue_cluster_id', 'court_type_id', 'name', 'status']);
        $currentCourtTypesSummary = $currentCourts
            ->map(fn (VenueCourt $court): ?string => $court->courtType?->name)
            ->filter()
            ->unique()
            ->implode('; ');
        $requestedCourtRows = $requestedCourts
            ->map(fn (array $court): array => [
                'name' => $court['name'] ?? '',
                'court_type_id' => $court['court_type_id'] ?? null,
                'court_type_name' => $typeNames[$court['court_type_id']] ?? null,
                'change' => 'Tăng/thêm sân',
                'effective_date' => $expectedEffectiveDate,
                'status' => 'Dự kiến hoạt động sau phê duyệt',
                'note' => 'Theo đơn yêu cầu đã ký',
            ])
            ->values()
            ->all();
        $removedCourtRows = $removedCourts
            ->map(fn (VenueCourt $court): array => [
                'id' => $court->id,
                'name' => $court->name,
                'court_type_id' => $court->court_type_id,
                'court_type_name' => $court->courtType?->name,
                'change' => 'Giảm/ngừng khai thác',
                'effective_date' => $expectedEffectiveDate,
                'status' => 'Dự kiến ngừng khai thác',
                'note' => 'Theo đơn yêu cầu đã ký',
            ])
            ->values()
            ->all();
        $courtChangeRows = array_values(array_merge($requestedCourtRows, $removedCourtRows));

        return [
            'owner_full_name' => $ownerSigner,
            'owner_signer_name' => $ownerSigner,
            'business_name' => $application?->business_name ?: $ownerSigner,
            'identity_number' => $application?->representative_identity_number,
            'tax_code' => $application?->tax_code,
            'business_license_number' => $application?->business_license_number ?: $application?->business_code,
            'owner_phone' => $application?->applicant_phone ?: $owner?->phone ?: $cluster->phone_contact,
            'owner_email' => $application?->applicant_email ?: $owner?->email,
            'owner_address' => $application?->business_address ?: $application?->applicant_address ?: $cluster->address,
            'venue_name' => $cluster->name,
            'cluster_name' => $cluster->name,
            'venue_cluster_id' => $cluster->id,
            'venue_cluster_code' => $cluster->slug ?: $cluster->id,
            'request_id' => $approvalRequest->id,
            'contract_code' => $contract?->contract_code,
            'contract_signed_at' => $this->contractTimelineAt($contract),
            'current_court_count' => $currentCourts->count(),
            'current_court_types_summary' => $currentCourtTypesSummary,
            'current_courts_summary' => $currentCourts
                ->map(fn (VenueCourt $court): string => trim($court->name . ' - ' . ($court->courtType?->name ?? '')))
                ->filter()
                ->implode('; '),
            'change_action' => $changeType === 'remove' ? 'Giam quy mo/xoa bot san con' : ($changeType === 'mixed' ? 'Dieu chinh quy mo san' : 'Mo rong quy mo/them san con'),
            'change_court_count' => $changeType === 'remove' ? '-' . $removedCourts->count() : (string) $requestedCourts->count(),
            'new_court_count' => $requestedCourts->count(),
            'removed_court_count' => $removedCourts->count(),
            'requested_court_type_name' => $approvalRequest->courtType?->name,
            'requested_court_names' => $newCourtsSummary ?: $approvalRequest->name,
            'new_court_name' => $newCourtsSummary ?: $approvalRequest->name,
            'new_courts_summary' => $newCourtsSummary,
            'removed_courts_summary' => $removedCourtsSummary,
            'requested_court_rows' => $requestedCourtRows,
            'removed_court_rows' => $removedCourtRows,
            'court_change_rows' => $courtChangeRows,
            'reason' => $approvalRequest->status_reason,
            'booking_impact' => 'Chủ sân cam kết rà soát booking, cấu hình giá và lịch vận hành trước khi SportGo cập nhật quy mô.',
            'submitted_at' => optional($approvalRequest->created_at)->format('d/m/Y H:i'),
            'expected_effective_date' => $expectedEffectiveDate,
        ];
    }

    private function partnerApplication(VenueCluster $cluster): ?PartnerApplication
    {
        return PartnerApplication::query()
            ->where('approved_venue_cluster_id', $cluster->id)
            ->latest('reviewed_at')
            ->latest('created_at')
            ->first();
    }

    private function partnerApplicationIdForCluster(?string $clusterId): ?string
    {
        if (! $clusterId) {
            return null;
        }

        return PartnerApplication::query()
            ->where('approved_venue_cluster_id', $clusterId)
            ->latest('reviewed_at')
            ->latest('created_at')
            ->value('id');
    }

    private function contractTimelineAt($contract)
    {
        return $contract?->sportgo_signed_at
            ?: $contract?->owner_signed_at
            ?: $contract?->effective_from
            ?: $contract?->created_at;
    }

    private function deleteDraftPreviewDocuments(string $documentType, VenueCluster $cluster): void
    {
        GeneratedDocument::query()
            ->where('document_type', $documentType)
            ->where('owner_id', $cluster->owner_id)
            ->where('venue_cluster_id', $cluster->id)
            ->where('status', 'draft_preview')
            ->get()
            ->each(function (GeneratedDocument $document): void {
                foreach ([$document->generated_file_path, $document->final_file_path] as $path) {
                    if ($path && Storage::disk('local')->exists($path)) {
                        Storage::disk('local')->delete($path);
                    }
                }

                $document->delete();
            });
    }

    private function documentPayload($document): ?array
    {
        if (! $document) {
            return null;
        }

        return [
            'id' => $document->id,
            'document_code' => $document->document_code,
            'document_type' => $document->document_type,
            'document_version' => $document->document_version,
            'title' => $document->title,
            'status' => $document->status,
            'file_hash' => $document->file_hash,
            'generated_at' => $document->generated_at,
            'download_url' => url('/api/files/documents/' . $document->id . '/download'),
        ];
    }
}
