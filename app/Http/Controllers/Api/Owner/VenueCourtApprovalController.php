<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Mail\Partner\VenueScaleRequestReceivedMail;
use App\Models\PartnerApplication;
use App\Models\VenueCluster;
use App\Models\VenueCourtApprovalRequest;
use App\Services\Partner\PartnerDocumentService;
use App\Services\Partner\PartnerProfileDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
    public function store(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền gửi yêu cầu cho cụm sân này.'], 403);
        }

        if ($cluster->status === 'locked') {
            return response()->json(['message' => 'Cụm sân đang bị khóa. Không thể gửi yêu cầu mới.'], 422);
        }

        $data = $request->validate([
            'court_type_id'  => ['required', 'integer', 'exists:court_types,id'],
            'name'           => ['required', 'string', 'max:100'],
            'note'           => ['nullable', 'string', 'max:1000'],
            'evidence_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
            'signature_image' => ['required', 'string', 'max:600000'],
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
        $evidencePath = null;
        if ($request->hasFile('evidence_image')) {
            $evidencePath = $request->file('evidence_image')
                ->store('approval-evidence/' . $clusterId, 'public');
        }

        $approvalRequest = VenueCourtApprovalRequest::create([
            'venue_cluster_id' => $clusterId,
            'court_type_id'    => $data['court_type_id'],
            'name'             => $data['name'],
            'status'           => 'pending',
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

        $signature = $this->storeSignatureImage($data['signature_image'], 'venue-change-signatures/scale/' . $clusterId, $approvalRequest->id);
        $approvalRequest->forceFill([
            'signature_image' => $signature['path'],
            'signature_hash' => $signature['hash'],
            'signed_at' => now(),
        ])->save();

        $this->generateAndSignScaleDocument($cluster, $approvalRequest, $request, $data['signature_image']);

        $approvalRequest->load(['courtType:id,name']);
        $this->sendOwnerMail($cluster, new VenueScaleRequestReceivedMail([
            'cluster_name' => $cluster->name,
            'court_name' => $approvalRequest->name,
            'court_type_name' => $approvalRequest->courtType?->name,
            'submitted_at' => now()->format('H:i d/m/Y'),
        ]), $approvalRequest->id);

        return response()->json([
            'message' => 'Gửi yêu cầu thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $this->payload($approvalRequest->load(['courtType:id,name', 'requestedBy:id,full_name,username,email,phone', 'generatedDocument.signatures'])),
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
            'signature_image' => ['required', 'string', 'max:600000'],
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

        $signature = $this->storeSignatureImage($data['signature_image'], 'venue-change-signatures/scale/' . $clusterId, $approvalRequest->id);

        $approvalRequest->forceFill([
            'status' => 'pending',
            'status_reason' => $data['note'] ?? 'Chủ sân đã bổ sung giấy tờ theo yêu cầu.',
            'supplementary_documents' => array_values(array_merge($approvalRequest->supplementary_documents ?: [], $documents)),
            'signature_image' => $signature['path'],
            'signature_hash' => $signature['hash'],
            'signed_at' => now(),
        ])->save();

        $this->generateAndSignScaleDocument($cluster, $approvalRequest->refresh(), $request, $data['signature_image']);

        return response()->json([
            'message' => 'Đã nộp giấy tờ bổ sung. Yêu cầu được chuyển lại về trạng thái chờ duyệt.',
            'data' => $this->payload($approvalRequest->fresh(['courtType:id,name', 'requestedBy:id,full_name,username,email,phone', 'reviewedBy:id,full_name,username', 'generatedDocument.signatures'])),
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

        if ($approvalRequest->status !== 'pending') {
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

    private function filesArray(mixed $files): array
    {
        return collect(\Illuminate\Support\Arr::wrap($files))
            ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile)
            ->values()
            ->all();
    }

    private function generateAndSignScaleDocument(VenueCluster $cluster, VenueCourtApprovalRequest $approvalRequest, Request $request, string $signatureImage): void
    {
        $approvalRequest->loadMissing(['courtType', 'requestedBy', 'venueCluster.owner']);
        $renderData = $this->scaleRequestRenderData($cluster, $approvalRequest);
        $document = $this->documents->generateDocument('venue_scale_request', $approvalRequest, $renderData, $request->user(), [
            'owner_id' => $cluster->owner_id,
            'venue_cluster_id' => $cluster->id,
            'entity_type' => VenueCluster::class,
            'entity_id' => $cluster->id,
            'status' => 'pending_owner_signature',
            'title' => 'Đơn yêu cầu mở rộng quy mô sân ' . $cluster->name,
        ]);

        $this->documents->signDocument($document, $request->user(), 'owner', $signatureImage, $request, [
            'signer_full_name' => $renderData['owner_signer_name'],
            'signer_title' => 'Chủ sân/Đối tác',
            'signature_method' => 'drawn',
        ]);

        $approvalRequest->forceFill(['generated_document_id' => $document->id])->save();
    }

    private function scaleRequestRenderData(VenueCluster $cluster, VenueCourtApprovalRequest $approvalRequest): array
    {
        $owner = $cluster->owner()->first();
        $application = $this->partnerApplication($cluster);
        $contract = $cluster->partnerContracts()->latest('created_at')->first();
        $ownerSigner = $application?->representative_name ?: ($owner?->full_name ?: $owner?->username ?: 'Chủ sân');

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
            'contract_code' => $contract?->contract_code,
            'contract_signed_at' => $contract?->completed_at ?: $contract?->created_at,
            'current_court_count' => $cluster->venueCourts()->count(),
            'change_action' => 'Mở rộng quy mô/thêm sân con',
            'change_court_count' => '1',
            'requested_court_type_name' => $approvalRequest->courtType?->name,
            'requested_court_names' => $approvalRequest->name,
            'new_court_name' => $approvalRequest->name,
            'reason' => $approvalRequest->status_reason,
            'submitted_at' => optional($approvalRequest->created_at)->format('d/m/Y H:i'),
            'expected_effective_date' => optional($approvalRequest->created_at)->format('d/m/Y'),
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
