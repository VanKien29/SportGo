<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Mail\Partner\VenueLocationChangeRequestReceivedMail;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\VenueCluster;
use App\Models\VenueLocationChangeRequest;
use App\Services\Partner\PartnerDocumentService;
use App\Services\Partner\PartnerLocationService;
use App\Services\Partner\PartnerMapResolver;
use App\Services\Partner\PartnerProfileDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VenueLocationChangeController extends Controller
{
    public function __construct(
        private readonly PartnerProfileDocumentService $profileDocuments,
        private readonly PartnerDocumentService $documents,
        private readonly PartnerLocationService $locations,
        private readonly PartnerMapResolver $maps,
    )
    {
    }

    /**
     * Lấy lịch sử yêu cầu thay đổi vị trí của cụm sân.
     */
    public function index(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xem yêu cầu của cụm sân này.'], 403);
        }

        $query = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->with(['requestedBy:id,full_name,username,email,phone', 'reviewedBy:id,full_name,username', 'generatedDocument.signatures'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->get()->map(fn ($r) => $this->payload($r));

        return response()->json(['data' => $requests]);
    }

    /**
     * Gửi yêu cầu thay đổi vị trí cụm sân.
     */
    public function preview(Request $request, string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền tạo bản xem trước cho cụm sân này.'], 403);
        }

        $data = $request->validate([
            'new_address'   => ['required', 'string', 'max:255'],
            'new_province'  => ['required', 'string', 'max:255'],
            'new_province_code' => ['required', 'string', 'max:20'],
            'new_ward'      => ['required', 'string', 'max:255'],
            'new_ward_code' => ['required', 'string', 'max:20'],
            'new_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'new_longitude' => ['required', 'numeric', 'between:-180,180'],
            'new_map_url'   => ['nullable', 'url', 'max:2000'],
            'note'          => ['required', 'string', 'max:1000'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
        ]);
        $data = $this->normalizeLocationRequestData($data);

        $this->assertLocationMatchesCoordinates($data);

        $previewRequest = new VenueLocationChangeRequest();
        $previewRequest->forceFill([
            'id' => (string) Str::uuid(),
            'venue_cluster_id' => $cluster->id,
            'requested_by' => $request->user()->id,
            'status' => 'draft_preview',
            'note' => $data['note'],
            'new_address' => $data['new_address'],
            'new_province' => $data['new_province'],
            'new_province_code' => $data['new_province_code'],
            'new_ward' => $data['new_ward'],
            'new_ward_code' => $data['new_ward_code'],
            'new_latitude' => $data['new_latitude'],
            'new_longitude' => $data['new_longitude'],
            'new_map_url' => $data['new_map_url'] ?? $this->googleMapsPointUrl((float) $data['new_latitude'], (float) $data['new_longitude']),
            'created_at' => now(),
        ]);
        $previewRequest->setRelation('requestedBy', $request->user());
        $previewRequest->setRelation('venueCluster', $cluster);

        $renderData = array_merge($this->locationRequestRenderData($cluster, $previewRequest), [
            'attachment_list' => $this->uploadedFileNames($request->file('supplementary_documents', [])),
        ]);

        $document = $this->documents->generateDocument('venue_location_change_request', $cluster, $renderData, $request->user(), [
            'reference_type' => 'venue_location_change_request_preview',
            'reference_id' => (string) Str::uuid(),
            'owner_id' => $cluster->owner_id,
            'venue_cluster_id' => $cluster->id,
            'entity_type' => VenueCluster::class,
            'entity_id' => $cluster->id,
            'status' => 'draft_preview',
            'title' => 'Bản xem trước đơn yêu cầu thay đổi vị trí cụm sân ' . $cluster->name,
        ]);

        return response()->json([
            'message' => 'Đã tạo bản xem trước đơn yêu cầu thay đổi vị trí.',
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

        // Kiểm tra xem đã có yêu cầu pending chưa
        $hasPending = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->whereIn('status', ['pending_owner_signature', 'pending', 'approved_pending_appendix', 'need_supplement'])
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'Bạn đã có yêu cầu thay đổi vị trí đang chờ xét duyệt. Vui lòng hủy yêu cầu cũ trước khi gửi yêu cầu mới.',
            ], 422);
        }

        $data = $request->validate([
            'new_address'   => ['required', 'string', 'max:255'],
            'new_province'  => ['required', 'string', 'max:255'],
            'new_province_code' => ['required', 'string', 'max:20'],
            'new_ward'      => ['required', 'string', 'max:255'],
            'new_ward_code' => ['required', 'string', 'max:20'],
            'new_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'new_longitude' => ['required', 'numeric', 'between:-180,180'],
            'new_map_url'   => ['nullable', 'url', 'max:2000'],
            'note'          => ['required', 'string', 'max:1000'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
            'preview_document_id' => ['nullable', 'uuid', 'exists:generated_documents,id'],
        ], [
            'new_address.required'   => 'Vui lòng nhập địa chỉ mới.',
            'new_province.required'  => 'Vui lòng nhập tỉnh/thành phố mới.',
            'new_ward.required'      => 'Vui lòng nhập phường/xã mới.',
            'new_latitude.required'  => 'Vui lòng nhập vĩ độ.',
            'new_latitude.between'   => 'Vĩ độ không hợp lệ.',
            'new_longitude.required' => 'Vui lòng nhập kinh độ.',
            'new_longitude.between'  => 'Kinh độ không hợp lệ.',
            'note.required'          => 'Vui lòng nhập lý do muốn thay đổi vị trí.',
            'supplementary_documents.required' => 'Vui lòng tải lên giấy ĐKKD/giấy cập nhật kinh doanh hoặc hình ảnh minh chứng vị trí mới.',
            'supplementary_documents.*.mimes' => 'Giấy tờ bổ sung phải có định dạng: jpg, jpeg, png, webp, pdf, doc, docx.',
            'supplementary_documents.*.max' => 'Mỗi giấy tờ bổ sung không được quá 10MB.',
            'signature_image.required' => 'Vui lòng ký xác nhận yêu cầu trước khi gửi.',
        ]);

        $data = $this->normalizeLocationRequestData($data);

        $this->assertLocationMatchesCoordinates($data);

        $locationRequest = VenueLocationChangeRequest::create([
            'venue_cluster_id' => $clusterId,
            'requested_by'     => $request->user()->id,
            'status'           => 'pending_owner_signature',
            'note'             => $data['note'],
            'new_address'      => $data['new_address'],
            'new_province'     => $data['new_province'],
            'new_province_code' => $data['new_province_code'],
            'new_ward'         => $data['new_ward'],
            'new_ward_code'    => $data['new_ward_code'],
            'new_latitude'     => $data['new_latitude'],
            'new_longitude'    => $data['new_longitude'],
            'new_map_url'      => $data['new_map_url'] ?? null,
        ]);
        $documents = $this->profileDocuments->attachVenueRequestDocuments(
            $cluster,
            $this->filesArray($request->file('supplementary_documents', [])),
            $locationRequest->id,
            'location_change_supplement',
            'location_change_documents',
            'Giấy tờ bổ sung yêu cầu thay đổi vị trí sân',
            'Giấy tờ chủ sân gửi kèm yêu cầu thay đổi vị trí sân.'
        );
        if ($documents !== []) {
            $locationRequest->forceFill(['supplementary_documents' => $documents])->save();
        }

        $this->generateLocationDocument($cluster, $locationRequest, $request, $data['preview_document_id'] ?? null);

        return response()->json([
            'message' => 'Gửi yêu cầu thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => [
                ...$this->payload($locationRequest->load(['requestedBy:id,full_name,username,email,phone', 'generatedDocument.signatures'])),
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

        $locationRequest = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if ($locationRequest->status !== 'need_supplement') {
            return response()->json(['message' => 'Chỉ có yêu cầu đang cần bổ sung mới được nộp thêm giấy tờ.'], 422);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'supplementary_documents' => ['required', 'array', 'min:1', 'max:10'],
            'supplementary_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf,doc,docx', 'max:10240'],
            'preview_document_id' => ['nullable', 'uuid', 'exists:generated_documents,id'],
        ], [
            'supplementary_documents.required' => 'Vui lòng tải lên ít nhất một giấy tờ bổ sung.',
            'supplementary_documents.*.mimes' => 'Giấy tờ bổ sung phải có định dạng: jpg, jpeg, png, webp, pdf, doc, docx.',
            'supplementary_documents.*.max' => 'Mỗi giấy tờ bổ sung không được quá 10MB.',
            'signature_image.required' => 'Vui lòng ký xác nhận yêu cầu trước khi gửi.',
        ]);

        $documents = $this->profileDocuments->attachVenueRequestDocuments(
            $cluster,
            $this->filesArray($request->file('supplementary_documents', [])),
            $locationRequest->id,
            'location_change_supplement',
            'location_change_documents',
            'Giấy tờ bổ sung yêu cầu thay đổi vị trí sân',
            'Giấy tờ chủ sân bổ sung theo yêu cầu của SportGo.'
        );

        $locationRequest->forceFill([
            'status' => 'pending_owner_signature',
            'status_reason' => $data['note'] ?? 'Chủ sân đã bổ sung giấy tờ theo yêu cầu.',
            'supplementary_documents' => array_values(array_merge($locationRequest->supplementary_documents ?: [], $documents)),
            'signature_image' => null,
            'signature_hash' => null,
            'signed_at' => null,
        ])->save();

        $this->generateLocationDocument($cluster, $locationRequest->refresh(), $request, $data['preview_document_id'] ?? null);

        return response()->json([
            'message' => 'Đã nộp giấy tờ bổ sung. Yêu cầu được chuyển lại về trạng thái chờ duyệt.',
            'data' => [
                ...$this->payload($locationRequest->fresh(['requestedBy:id,full_name,username,email,phone', 'reviewedBy:id,full_name,username', 'generatedDocument.signatures'])),
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

        $locationRequest = VenueLocationChangeRequest::query()
            ->where('venue_cluster_id', $clusterId)
            ->findOrFail($requestId);

        if (! in_array($locationRequest->status, ['pending_owner_signature', 'pending'], true)) {
            return response()->json(['message' => 'Chỉ có thể hủy yêu cầu đang ở trạng thái chờ duyệt.'], 422);
        }

        $locationRequest->forceFill(['status' => 'cancelled'])->save();

        return response()->json([
            'message' => 'Đã hủy yêu cầu.',
            'data'    => $this->payload($locationRequest->fresh(['requestedBy'])),
        ]);
    }

    private function sendOwnerMail(VenueCluster $cluster, Mailable $mail, ?string $referenceId = null): void
    {
        $owner = $cluster->owner()->first();
        if (! $owner?->email) {
            Log::warning('Venue location request mail skipped: owner has no email.', [
                'venue_cluster_id' => $cluster->id,
                'reference_id' => $referenceId,
            ]);
            return;
        }

        try {
            Mail::to($owner->email)->send($mail);
        } catch (\Throwable $exception) {
            Log::error('Venue location request mail failed.', [
                'venue_cluster_id' => $cluster->id,
                'reference_id' => $referenceId,
                'owner_id' => $owner->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function payload(VenueLocationChangeRequest $r): array
    {
        return [
            'id'            => $r->id,
            'status'        => $r->status,
            'note'          => $r->note,
            'status_reason' => $r->status_reason,
            'new_address'   => $r->new_address,
            'new_province'  => $r->new_province,
            'new_province_code' => $r->new_province_code,
            'new_ward'      => $r->new_ward,
            'new_ward_code' => $r->new_ward_code,
            'new_latitude'  => $r->new_latitude,
            'new_longitude' => $r->new_longitude,
            'new_map_url'   => $r->new_map_url,
            'supplementary_documents' => $r->supplementary_documents ?: [],
            'signature_image' => $r->signature_image,
            'signature_image_url' => $r->signature_image ? asset('storage/' . $r->signature_image) : null,
            'signature_hash' => $r->signature_hash,
            'signed_at' => $r->signed_at,
            'generated_document' => $this->documentPayload($r->generatedDocument),
            'appendix_document' => $this->documentPayload($this->appendixDocumentForRequest($r, 'venue_location_appendix')),
            'partner_application_id' => $this->partnerApplicationIdForCluster($r->venue_cluster_id),
            'requested_by'  => $r->requestedBy ? [
                'id'        => $r->requestedBy->id,
                'full_name' => $r->requestedBy->full_name,
            ] : null,
            'reviewed_by'   => $r->reviewedBy ? [
                'id'        => $r->reviewedBy->id,
                'full_name' => $r->reviewedBy->full_name,
            ] : null,
            'reviewed_at'   => $r->reviewed_at,
            'created_at'    => $r->created_at,
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

    private function normalizeLocationRequestData(array $data): array
    {
        if (! $this->locations->assertWardBelongsToProvince($data['new_province_code'], $data['new_ward_code'])) {
            throw ValidationException::withMessages([
                'new_ward_code' => 'Phường/Xã không thuộc Tỉnh/Thành phố đã chọn.',
            ]);
        }

        $province = $this->locations->provinceByCode($data['new_province_code']);
        $ward = $this->locations->wardByCode($data['new_province_code'], $data['new_ward_code']);

        $data['new_province'] = $province['name'] ?? ($data['new_province'] ?? '');
        $data['new_ward'] = $ward['name'] ?? ($data['new_ward'] ?? '');

        return $data;
    }

    private function assertLocationMatchesCoordinates(array $data): void
    {
        try {
            $resolved = $this->maps->reverse((float) $data['new_latitude'], (float) $data['new_longitude']);
        } catch (\Throwable $exception) {
            Log::warning('Venue location reverse validation skipped.', [
                'latitude' => $data['new_latitude'] ?? null,
                'longitude' => $data['new_longitude'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $errors = [];
        $resolvedProvinceCode = (string) ($resolved['province_code'] ?? '');
        $resolvedWardCode = (string) ($resolved['ward_code'] ?? '');

        if ($resolvedProvinceCode !== '' && $resolvedProvinceCode !== (string) ($data['new_province_code'] ?? '')) {
            $errors['new_province_code'] = 'Tỉnh/Thành phố không khớp với tọa độ đã chọn trên bản đồ.';
        }

        if ($resolvedWardCode !== '' && $resolvedWardCode !== (string) ($data['new_ward_code'] ?? '')) {
            $errors['new_ward_code'] = 'Phường/Xã không khớp với tọa độ đã chọn trên bản đồ.';
        }

        if ($resolvedProvinceCode !== '' || $resolvedWardCode !== '') {
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            return;
        }
        if (! empty($resolved['province']) && ! $this->sameLocationName($resolved['province'], $data['new_province'] ?? null)) {
            $errors['new_province'] = 'Tỉnh/Thành phố không khớp với tọa độ đã chọn trên bản đồ.';
        }

        if (! empty($resolved['ward']) && ! $this->sameLocationName($resolved['ward'], $data['new_ward'] ?? null)) {
            $errors['new_ward'] = 'Phường/Xã không khớp với tọa độ đã chọn trên bản đồ.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function sameLocationName(?string $actual, ?string $selected): bool
    {
        $actual = $this->normalizeLocationName($actual);
        $selected = $this->normalizeLocationName($selected);

        if ($actual === '' || $selected === '') {
            return true;
        }

        return $actual === $selected
            || str_contains($actual, $selected)
            || str_contains($selected, $actual);
    }

    private function normalizeLocationName(?string $value): string
    {
        return trim(Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\b(thanh pho|tinh|quan|huyen|thi xa|phuong|xa|thi tran|tp)\b/u', '')
            ->replaceMatches('/[^a-z0-9]+/u', '')
            ->toString());
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

    private function googleMapsPointUrl(float $latitude, float $longitude): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude;
    }

    private function generateLocationDocument(VenueCluster $cluster, VenueLocationChangeRequest $locationRequest, Request $request, ?string $previewDocumentId = null): void
    {
        $locationRequest->loadMissing(['requestedBy', 'venueCluster.owner']);
        $renderData = $this->locationRequestRenderData($cluster, $locationRequest);
        $document = $previewDocumentId
            ? GeneratedDocument::query()
                ->whereKey($previewDocumentId)
                ->where('document_type', 'venue_location_change_request')
                ->where('owner_id', $cluster->owner_id)
                ->where('venue_cluster_id', $cluster->id)
                ->where('status', 'draft_preview')
                ->first()
            : null;

        if ($document) {
            $document->forceFill([
                'reference_type' => VenueLocationChangeRequest::class,
                'reference_id' => $locationRequest->id,
                'partner_application_id' => $this->partnerApplication($cluster)?->id,
                'entity_type' => VenueCluster::class,
                'entity_id' => $cluster->id,
                'status' => 'pending_owner_signature',
                'render_data' => array_merge($document->render_data ?: [], $renderData),
                'title' => 'Đơn yêu cầu thay đổi vị trí cụm sân ' . $cluster->name,
            ])->save();
        } else {
            $document = $this->documents->generateDocument('venue_location_change_request', $locationRequest, $renderData, $request->user(), [
                'owner_id' => $cluster->owner_id,
                'venue_cluster_id' => $cluster->id,
                'partner_application_id' => $this->partnerApplication($cluster)?->id,
                'entity_type' => VenueCluster::class,
                'entity_id' => $cluster->id,
                'status' => 'pending_owner_signature',
                'title' => 'Đơn yêu cầu thay đổi vị trí cụm sân ' . $cluster->name,
            ]);
        }

        $locationRequest->forceFill(['generated_document_id' => $document->id])->save();
    }

    private function locationRequestRenderData(VenueCluster $cluster, VenueLocationChangeRequest $locationRequest): array
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
            'request_id' => $locationRequest->id,
            'contract_code' => $contract?->contract_code,
            'contract_signed_at' => $this->contractTimelineAt($contract),
            'current_address' => $cluster->address,
            'current_province' => $cluster->province,
            'current_ward' => $cluster->ward,
            'current_latitude' => $cluster->latitude,
            'current_longitude' => $cluster->longitude,
            'current_map_url' => $cluster->map_url,
            'venue_phone' => $cluster->phone_contact,
            'venue_manager_name' => $ownerSigner,
            'new_address' => $locationRequest->new_address,
            'new_province' => $locationRequest->new_province,
            'new_ward' => $locationRequest->new_ward,
            'new_latitude' => $locationRequest->new_latitude,
            'new_longitude' => $locationRequest->new_longitude,
            'new_map_url' => $locationRequest->new_map_url,
            'new_phone' => $cluster->phone_contact,
            'reason' => $locationRequest->note ?: $locationRequest->status_reason,
            'booking_impact' => 'Chủ sân cam kết rà soát và xử lý các booking bị ảnh hưởng trước khi SportGo cập nhật vị trí.',
            'submitted_at' => optional($locationRequest->created_at)->format('d/m/Y H:i'),
            'expected_effective_date' => optional($locationRequest->created_at)->format('d/m/Y'),
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

    private function appendixDocumentForRequest(VenueLocationChangeRequest $requestModel, string $documentType): ?GeneratedDocument
    {
        return GeneratedDocument::query()
            ->with('signatures')
            ->where('document_type', $documentType)
            ->where('reference_type', $requestModel::class)
            ->where('reference_id', (string) $requestModel->getKey())
            ->latest('document_version')
            ->latest('generated_at')
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
            'partner_application_id' => $document->partner_application_id,
            'partner_contract_id' => $document->partner_contract_id,
            'owner_id' => $document->owner_id,
            'venue_cluster_id' => $document->venue_cluster_id,
            'title' => $document->title,
            'status' => $document->status,
            'file_hash' => $document->file_hash,
            'generated_at' => $document->generated_at,
            'download_url' => url('/api/files/documents/' . $document->id . '/download'),
            'signatures' => $document->relationLoaded('signatures')
                ? $document->signatures->values()
                : $document->signatures()->get(),
        ];
    }
}
