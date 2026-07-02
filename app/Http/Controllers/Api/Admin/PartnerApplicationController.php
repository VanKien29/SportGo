<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourtType;
use App\Models\DocumentSigningRequest;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Models\PartnerTerminationRequest;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerDocumentSigningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly PartnerApplicationService $partners,
        private readonly PartnerDocumentSigningService $signing,
    )
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = PartnerApplication::query()
            ->with([
                'user:id,full_name,username,email,phone',
                'reviewedBy:id,full_name,username,email',
                'approvedVenueCluster:id,name,status',
            ])
            ->withCount('courts');

        if ($request->filled('tab')) {
            match ($request->input('tab')) {
                'all' => null,
                'pending',
                'pending_review' => $query->whereIn('status', ['pending', 'reviewing', 'submitted', 'need_supplement']),
                'pending_signature' => $query->whereIn('status', ['contract_pending_owner_signature', 'contract_pending_sportgo_signature']),
                'active' => $query->where('status', 'completed'),
                'terminating' => $query->whereHas('terminationRequests', fn ($q) => $q->whereIn('status', ['submitted', 'reviewing', 'transition_period'])),
                'terminated' => $query->whereNotNull('terminated_at'),
                'rejected' => $query->whereIn('status', ['rejected', 'cancelled']),
                default => null,
            };
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('business_name', 'like', $search)
                    ->orWhere('venue_name', 'like', $search)
                    ->orWhere('tax_code', 'like', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('full_name', 'like', $search)
                            ->orWhere('username', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('submitted_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('submitted_at', '<=', $request->input('date_to'));
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $page = max((int) $request->integer('page', 1), 1);
        $partners = $query
            ->with(['contracts', 'terminationRequests'])
            ->orderByDesc('submitted_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($items) => $this->partnerRowPayload($items))
            ->values();

        $applications = new LengthAwarePaginator(
            $partners->forPage($page, $perPage)->values(),
            $partners->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json(['status' => 'success', 'data' => $applications]);
    }

    private function partnerRowPayload($applications): array
    {
        $items = collect($applications);
        /** @var PartnerApplication $latest */
        $latest = $items
            ->sortByDesc(fn (PartnerApplication $item) => $item->submitted_at ?: $item->created_at)
            ->first();
        $user = $latest->user;
        $contracts = $items
            ->flatMap(fn (PartnerApplication $item) => $item->contracts ?: collect())
            ->sortByDesc(fn (PartnerContract $contract) => $contract->created_at);
        $terminations = $items
            ->flatMap(fn (PartnerApplication $item) => $item->terminationRequests ?: collect())
            ->sortByDesc(fn (PartnerTerminationRequest $termination) => $termination->created_at);
        $clusterIds = $items
            ->pluck('approved_venue_cluster_id')
            ->filter()
            ->unique()
            ->values();

        return [
            ...$this->payload($latest),
            'partner_code' => 'PTN-' . strtoupper(substr(str_replace('-', '', (string) $latest->user_id), 0, 8)),
            'partner_name' => $user?->full_name ?: $latest->applicant_full_name,
            'partner_phone' => $user?->phone ?: $latest->applicant_phone,
            'partner_email' => $user?->email ?: $latest->applicant_email,
            'latest_application_id' => $latest->id,
            'application_count' => $items->count(),
            'managed_clusters_count' => $clusterIds->count(),
            'partner_status' => $this->aggregatePartnerStatus($items, $terminations->first()),
            'contract_status' => $contracts->first()?->status,
            'latest_registered_at' => $latest->submitted_at ?: $latest->created_at,
            'venue_names' => $items->pluck('venue_name')->filter()->unique()->values(),
        ];
    }

    private function aggregatePartnerStatus($applications, ?PartnerTerminationRequest $termination): string
    {
        $statuses = collect($applications)->pluck('status');

        if ($termination && in_array($termination->status, ['submitted', 'reviewing', 'transition_period'], true)) {
            return 'terminating';
        }

        if ($statuses->contains('completed')) {
            return 'completed';
        }

        if ($statuses->contains(fn ($status) => in_array($status, ['contract_pending_owner_signature', 'contract_pending_sportgo_signature'], true))) {
            return 'pending_signature';
        }

        if ($statuses->contains(fn ($status) => in_array($status, ['pending', 'reviewing', 'submitted', 'need_supplement'], true))) {
            return 'pending_review';
        }

        if ($statuses->contains('rejected')) {
            return 'rejected';
        }

        if ($statuses->contains('cancelled')) {
            return 'cancelled';
        }

        return (string) ($statuses->first() ?: 'unknown');
    }

    public function show(string $id): JsonResponse
    {
        $application = PartnerApplication::with($this->partners->detailRelations())->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->payload($application, true),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $application = PartnerApplication::with(['courts', 'bankAccounts', 'user'])->findOrFail($id);
        $data = $request->validate([
            'initial_court_name' => [Rule::requiredIf($application->courts->isEmpty()), 'nullable', 'string', 'max:100'],
            'court_type_id' => [Rule::requiredIf($application->courts->isEmpty()), 'nullable', 'integer', 'exists:court_types,id'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($application->courts->isEmpty() && ! empty($data['court_type_id'])) {
            $courtType = CourtType::query()
                ->withCount('children')
                ->find((int) $data['court_type_id']);

            if (! $courtType || ! $courtType->is_active || (int) $courtType->children_count > 0) {
                throw ValidationException::withMessages([
                    'court_type_id' => 'Vui lòng chọn loại sân con đang hoạt động, không chọn loại sân cha.',
                ]);
            }
        }

        $application = $this->partners->approve($application, $request->user(), $data, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Duyệt hồ sơ thành công. Hợp đồng đã được tạo và đang chờ SportGo ký.',
            'data' => $this->payload($application, true),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'action_type' => ['nullable', Rule::in(['reject', 'need_supplement'])],
        ]);

        $source = PartnerApplication::with('user')->findOrFail($id);
        $isSupplement = ($data['action_type'] ?? 'reject') === 'need_supplement';
        $application = $isSupplement
            ? $this->partners->requireSupplement($source, $request->user(), $data['reason'], $request)
            : $this->partners->reject($source, $request->user(), $data['reason'], $request);

        return response()->json([
            'status' => 'success',
            'message' => $isSupplement ? 'Đã yêu cầu người dùng bổ sung hồ sơ.' : 'Từ chối hồ sơ thành công.',
            'data' => $this->payload($application, true),
        ]);
    }

    public function signDocument(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'contract_id' => ['nullable', 'string', 'exists:partner_contracts,id'],
            'signature_image' => ['required', 'string'],
        ]);

        $application = PartnerApplication::findOrFail($id);
        $contract = PartnerContract::query()
            ->where('partner_application_id', $application->id)
            ->when($request->filled('contract_id'), fn ($q) => $q->whereKey($request->input('contract_id')))
            ->where('status', 'pending_sportgo_signature')
            ->latest()
            ->first();

        if (! $contract) {
            throw ValidationException::withMessages(['contract' => 'Không có hợp đồng đang chờ SportGo ký.']);
        }

        $contract = $this->partners->signAdminContract($contract, $request->user(), $request, $request->input('signature_image'));

        return response()->json([
            'status' => 'success',
            'message' => 'SportGo đã ký hợp đồng. Người dùng sẽ được thông báo để vào hệ thống ký xác nhận.',
            'data' => $this->payload($contract->application->fresh($this->partners->detailRelations()), true),
        ]);
    }



    public function terminate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $application = PartnerApplication::findOrFail($id);
        $contract = PartnerContract::query()
            ->where('partner_application_id', $application->id)
            ->where('status', 'signed_active')
            ->latest()
            ->firstOrFail();

        $termination = $this->partners->initiateUnilateralTermination($contract, $request->user(), $request, $data['reason']);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã khởi tạo chấm dứt hợp tác đơn phương.',
            'data' => $termination,
        ]);
    }

    public function confirmTermination(Request $request, string $id): JsonResponse
    {
        $application = PartnerApplication::findOrFail($id);
        $terminationId = $request->input('termination_request_id');
        $termination = PartnerTerminationRequest::query()
            ->where('partner_application_id', $application->id)
            ->when($terminationId, fn ($q) => $q->whereKey($terminationId))
            ->whereIn('status', ['submitted', 'reviewing'])
            ->latest()
            ->firstOrFail();

        $termination = $this->partners->confirmTermination($termination, $request->user(), $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã xác nhận yêu cầu chấm dứt và tạo quyết toán.',
            'data' => $termination,
        ]);
    }

    private function payload(PartnerApplication $application, bool $detail = false): array
    {
        $user = $application->user;
        $payload = [
            'id' => $application->id,
            'user_id' => $application->user_id,
            'applicant_full_name' => $application->applicant_full_name,
            'applicant_phone' => $application->applicant_phone,
            'applicant_email' => $application->applicant_email,
            'applicant_birth_date' => $application->applicant_birth_date,
            'applicant_address' => $application->applicant_address,
            'applicant_type' => $application->applicant_type,
            'representative_name' => $application->representative_name,
            'representative_identity_type' => $application->representative_identity_type,
            'representative_identity_number' => $application->representative_identity_number,
            'representative_identity_issued_date' => $application->representative_identity_issued_date,
            'representative_identity_issued_place' => $application->representative_identity_issued_place,
            'representative_position' => $application->representative_position,
            'business_name' => $application->business_name,
            'business_code' => $application->business_code,
            'tax_code' => $application->tax_code,
            'business_license_number' => $application->business_license_number,
            'business_address' => $application->business_address,
            'venue_name' => $application->venue_name,
            'venue_address' => $application->venue_address,
            'venue_province' => $application->venue_province,
            'venue_province_code' => $application->venue_province_code,
            'venue_district' => $application->venue_district,
            'venue_district_code' => $application->venue_district_code,
            'venue_ward' => $application->venue_ward,
            'venue_ward_code' => $application->venue_ward_code,
            'venue_map_url' => $application->venue_map_url,
            'venue_latitude' => $application->venue_latitude,
            'venue_longitude' => $application->venue_longitude,
            'venue_phone' => $application->venue_phone,
            'venue_email' => $application->venue_email,
            'venue_description' => $application->venue_description,
            'expected_opening_hours' => $application->expected_opening_hours,
            'parking_info' => $application->parking_info,
            'amenities' => $application->amenities,
            'court_count_total' => $application->court_count_total,
            'base_price_per_hour' => $application->base_price_per_hour,
            'bank_name' => $application->bank_name,
            'bank_code' => $application->bank_code,
            'account_number' => $application->account_number,
            'account_holder_name' => $application->account_holder_name,
            'bank_branch' => $application->bank_branch,
            'bank_verification_status' => $application->bank_verification_status,
            'bank_verified_at' => $application->bank_verified_at,
            'status' => $application->status,
            'status_reason' => $application->status_reason,
            'approved_venue_cluster_id' => $application->approved_venue_cluster_id,
            'submitted_at' => $application->submitted_at,
            'reviewed_at' => $application->reviewed_at,
            'terminated_at' => $application->terminated_at,
            'courts_count' => $application->courts_count ?? $application->courts?->count() ?? 0,
            'user' => $user ? [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
            ] : null,
            'reviewed_by' => $application->reviewedBy ? [
                'id' => $application->reviewedBy->id,
                'full_name' => $application->reviewedBy->full_name,
                'email' => $application->reviewedBy->email,
            ] : null,
            'approved_venue_cluster' => $application->approvedVenueCluster,
        ];

        if (! $detail) {
            return $payload;
        }

        $payload['courts'] = $application->courts->values();
        $payload['bank_accounts'] = $application->bankAccounts->values();
        $payload['documents'] = GeneratedDocument::with(['signatures.signer', 'signingRequests.user', 'signingRequests.signature', 'signingRequests.verificationCode'])
            ->where('partner_application_id', $application->id)
            ->latest()
            ->get()
            ->map(function (GeneratedDocument $document) {
                $path = $document->final_file_path ?: $document->generated_file_path;
                $fileAvailable = (bool) ($path && Storage::disk('local')->exists($path));

                return [
                    'id' => $document->id,
                    'partner_application_id' => $document->partner_application_id,
                    'partner_contract_id' => $document->partner_contract_id,
                    'document_code' => $document->document_code,
                    'document_type' => $document->document_type,
                    'title' => $document->title,
                    'status' => $document->status,
                    'generated_at' => $document->generated_at,
                    'file_available' => $fileAvailable,
                    'file_size' => $fileAvailable ? Storage::disk('local')->size($path) : 0,
                    'download_url' => $fileAvailable ? '/api/files/documents/' . $document->id . '/download' : null,
                    'signatures' => $document->signatures,
                    'signing_requests' => $document->signingRequests
                        ->sortByDesc('created_at')
                        ->map(fn (DocumentSigningRequest $request): array => $this->signingRequestPayload($request, $document))
                        ->values(),
                ];
            });
        $payload['uploaded_documents'] = $application->documents
            ->values()
            ->map(fn ($document) => [
                'id' => $document->id,
                'partner_application_id' => $document->partner_application_id,
                'document_type' => $document->document_type,
                'document_group' => $document->document_group,
                'title' => $document->title,
                'description' => $document->description,
                'status' => $document->status,
                'reject_reason' => $document->reject_reason,
                'reviewed_at' => $document->reviewed_at,
                'file_name' => $document->media?->file_name,
                'mime_type' => $document->media?->mime_type,
                'file_size' => $document->media?->file_size,
                'uploaded_at' => $document->created_at,
                'download_url' => '/api/admin/partner-profiles/documents/' . $document->id . '/download',
            ]);
        $payload['contracts'] = $application->contracts;
        $payload['status_histories'] = $application->statusHistories;
        $payload['termination_requests'] = $application->terminationRequests;
        $partnerApplications = PartnerApplication::query()
            ->with(['approvedVenueCluster:id,name,status,address', 'contracts'])
            ->withCount('courts')
            ->where('user_id', $application->user_id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->get();

        $payload['partner_summary'] = [
            'partner_code' => 'PTN-' . strtoupper(substr(str_replace('-', '', (string) $application->user_id), 0, 8)),
            'application_count' => $partnerApplications->count(),
            'managed_clusters_count' => $partnerApplications->pluck('approved_venue_cluster_id')->filter()->unique()->count(),
            'active_clusters_count' => $partnerApplications->where('status', 'completed')->count(),
            'latest_registered_at' => $partnerApplications->first()?->submitted_at ?: $partnerApplications->first()?->created_at,
        ];

        $payload['partner_applications'] = $partnerApplications
            ->map(function (PartnerApplication $item): array {
                $contract = $item->contracts->sortByDesc('created_at')->first();

                return [
                    'id' => $item->id,
                    'venue_name' => $item->venue_name,
                    'venue_address' => $item->venue_address,
                    'venue_province' => $item->venue_province,
                    'venue_ward' => $item->venue_ward,
                    'status' => $item->status,
                    'status_reason' => $item->status_reason,
                    'contract_status' => $contract?->status,
                    'contract_code' => $contract?->contract_code,
                    'approved_venue_cluster_id' => $item->approved_venue_cluster_id,
                    'approved_venue_cluster' => $item->approvedVenueCluster,
                    'courts_count' => $item->courts_count,
                    'submitted_at' => $item->submitted_at,
                    'reviewed_at' => $item->reviewed_at,
                    'terminated_at' => $item->terminated_at,
                ];
            })
            ->values();

        return $payload;
    }

    private function signingRequestPayload(DocumentSigningRequest $request, GeneratedDocument $document): array
    {
        return [
            'id' => $request->id,
            'document_id' => $request->generated_document_id,
            'document_type' => $request->document_type,
            'document_code' => $request->document_code,
            'document_version' => $request->document_version,
            'signer_side' => $request->signer_side,
            'signer_role' => $request->signer_side === 'sportgo' ? 'Đại diện SportGo' : 'Đối tác/chủ sân',
            'signer' => $request->user ? [
                'id' => $request->user->id,
                'full_name' => $request->user->full_name,
                'email' => $request->user->email,
                'phone' => $request->user->phone,
            ] : null,
            'action' => $request->action,
            'otp_reference' => substr((string) $request->nonce, 0, 12),
            'otp_channel' => $request->otp_channel,
            'otp_identifier' => $request->otp_identifier,
            'otp_sent_at' => $request->otp_sent_at,
            'otp_verified_at' => $request->otp_verified_at,
            'otp_status' => $request->status,
            'expires_at' => $request->expires_at,
            'attempt_count' => $request->verificationCode?->attempt_count,
            'max_attempts' => $request->verificationCode?->max_attempts,
            'file_hash_before' => $request->file_hash,
            'file_hash_after' => $request->file_hash_after,
            'hash_short' => substr((string) ($request->file_hash_after ?: $request->file_hash ?: $document->file_hash), 0, 16),
            'ip_address' => $request->ip_address,
            'user_agent' => $request->user_agent,
            'device' => $this->deviceLabel($request->user_agent),
            'checkbox_text' => $request->checkbox_text,
            'signature_position' => $request->metadata['signature_position'] ?? $this->signaturePosition($document->document_type, $request->signer_side),
            'signature_id' => $request->signed_signature_id,
            'signed_at' => $request->signature?->signed_at,
            'status' => $request->status,
            'created_at' => $request->created_at,
        ];
    }

    private function signaturePosition(?string $documentType, ?string $side): string
    {
        return match ($documentType . ':' . $side) {
            'partner_application_form:owner' => 'Khối NGƯỜI ĐỀ NGHỊ / placeholder {{signature_owner}}',
            'partner_contract:sportgo' => 'Khối ĐẠI DIỆN BÊN A - SPORTGO / placeholder {{signature_sportgo}}',
            'partner_contract:owner' => 'Khối ĐẠI DIỆN BÊN B - ĐỐI TÁC/CHỦ SÂN / placeholder {{signature_owner}}',
            'owner_termination_request:owner',
            'termination_request:owner' => 'Khối NGƯỜI LÀM ĐƠN / placeholder {{signature_owner}}',
            'mutual_liquidation_minutes:sportgo' => 'Khối ĐẠI DIỆN SPORTGO / placeholder {{signature_sportgo}}',
            'mutual_liquidation_minutes:owner' => 'Khối ĐẠI DIỆN ĐỐI TÁC / placeholder {{signature_owner}}',
            'settlement_minutes:sportgo' => 'Khối ĐẠI DIỆN SPORTGO / placeholder {{signature_sportgo}}',
            'settlement_minutes:owner' => 'Khối ĐẠI DIỆN ĐỐI TÁC / placeholder {{signature_owner}}',
            default => 'Theo cấu hình placeholder chữ ký của template',
        };
    }

    private function deviceLabel(?string $userAgent): string
    {
        if (! $userAgent) {
            return '-';
        }

        $agent = strtolower($userAgent);
        $device = str_contains($agent, 'mobile') ? 'Mobile' : (str_contains($agent, 'tablet') ? 'Tablet' : 'Desktop');
        $browser = str_contains($agent, 'edg') ? 'Edge'
            : (str_contains($agent, 'chrome') ? 'Chrome'
                : (str_contains($agent, 'firefox') ? 'Firefox'
                    : (str_contains($agent, 'safari') ? 'Safari' : 'Trình duyệt')));

        return $device . ' / ' . $browser;
    }
}
