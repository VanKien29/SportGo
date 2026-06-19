<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Services\Partner\PartnerApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerApplicationController extends Controller
{
    public function __construct(private readonly PartnerApplicationService $partners)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $applications = PartnerApplication::with($this->partners->detailRelations())
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'latest' => $applications->first(),
                'history' => $applications,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'applicant_full_name' => ['nullable', 'string', 'max:255'],
            'applicant_phone' => ['nullable', 'string', 'max:30'],
            'applicant_email' => ['nullable', 'email', 'max:255'],
            'applicant_address' => ['nullable', 'string'],
            'representative_identity_number' => ['nullable', 'string', 'max:50'],
            'business_name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string'],
            'venue_province' => ['nullable', 'string', 'max:100'],
            'venue_district' => ['nullable', 'string', 'max:100'],
            'venue_ward' => ['nullable', 'string', 'max:100'],
            'venue_map_url' => ['nullable', 'url', 'max:1000'],
            'venue_latitude' => ['required', 'numeric'],
            'venue_longitude' => ['required', 'numeric'],
            'venue_phone' => ['nullable', 'string', 'max:30'],
            'venue_email' => ['nullable', 'email', 'max:255'],
            'venue_description' => ['nullable', 'string'],
            'amenities' => ['nullable', 'array'],
            'court_count_total' => ['nullable', 'integer', 'min:0'],
            'courts' => ['nullable', 'array'],
            'courts.*.court_type_id' => ['required_with:courts', 'integer', 'exists:court_types,id'],
            'courts.*.name' => ['required_with:courts', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_code' => ['nullable', 'string', 'max:50'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
        ]);

        $application = $this->partners->submitApplication($request->user(), $data, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi hồ sơ đăng ký đối tác.',
            'data' => $application,
        ], 201);
    }

    public function pendingContract(Request $request): JsonResponse
    {
        $contract = PartnerContract::with(['application', 'generatedDocument.signatures'])
            ->where('owner_id', $request->user()->id)
            ->where('status', 'pending_owner_signature')
            ->latest()
            ->first();

        if (! $contract) {
            return response()->json(['status' => 'success', 'data' => null]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'contract' => $contract,
                'document' => $contract->generatedDocument,
                'download_url' => $contract->generatedDocument ? url('/api/files/documents/' . $contract->generatedDocument->id . '/download') : null,
            ],
        ]);
    }

    public function signContract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => ['nullable', 'string', 'exists:partner_contracts,id'],
            'signature_image' => ['nullable', 'string'],
        ]);

        $contract = PartnerContract::with(['application.user', 'generatedDocument'])
            ->where('owner_id', $request->user()->id)
            ->where('status', 'pending_owner_signature')
            ->when($data['contract_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->latest()
            ->firstOrFail();

        $contract = $this->partners->signOwnerContract($contract, $request->user(), $request, $data['signature_image'] ?? null);

        return response()->json([
            'status' => 'success',
            'message' => 'Bạn đã ký hợp đồng thành công. SportGo sẽ ký xác nhận và gửi hợp đồng chính thức về email của bạn.',
            'data' => $contract,
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $applicationIds = PartnerApplication::query()->where('user_id', $request->user()->id)->pluck('id');
        $documents = GeneratedDocument::query()
            ->whereIn('partner_application_id', $applicationIds)
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => $documents]);
    }
}
