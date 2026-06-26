<?php

namespace App\Http\Controllers\Api\Owner;

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

    public function myApplications(Request $request): JsonResponse
    {
        $applications = PartnerApplication::with($this->partners->detailRelations())
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['status' => 'success', 'data' => $applications]);
    }

    public function myApplication(Request $request): JsonResponse
    {
        $application = PartnerApplication::with($this->partners->detailRelations())
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (! $application) {
            return response()->json(['status' => 'success', 'data' => null]);
        }

        return response()->json(['status' => 'success', 'data' => $application]);
    }

    public function documents(Request $request): JsonResponse
    {
        $applicationIds = PartnerApplication::query()
            ->where('user_id', $request->user()->id)
            ->pluck('id');

        $documents = GeneratedDocument::with('signatures.signer')
            ->whereIn('partner_application_id', $applicationIds)
            ->latest()
            ->get()
            ->map(fn (GeneratedDocument $document) => [
                'id' => $document->id,
                'partner_application_id' => $document->partner_application_id,
                'document_code' => $document->document_code,
                'document_type' => $document->document_type,
                'title' => $document->title,
                'status' => $document->status,
                'generated_at' => $document->generated_at,
                'download_url' => '/api/files/documents/' . $document->id . '/download',
                'signatures' => $document->signatures,
            ]);

        return response()->json(['status' => 'success', 'data' => $documents]);
    }

    public function requestTermination(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contract_id' => ['nullable', 'string', 'exists:partner_contracts,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'signature_image' => ['nullable', 'string'],
        ]);

        $contract = PartnerContract::query()
            ->where('owner_id', $request->user()->id)
            ->where('status', 'signed_active')
            ->when($data['contract_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->latest()
            ->firstOrFail();

        $termination = $this->partners->requestTermination(
            $contract,
            $request->user(),
            $request,
            $data['reason'],
            $data['signature_image'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi yêu cầu chấm dứt hợp tác.',
            'data' => $termination,
        ]);
    }

    public function storeNewCluster(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:50'],
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
            'expected_opening_hours' => ['nullable', 'string', 'max:255'],
            'parking_info' => ['nullable', 'string'],
            'amenities' => ['nullable', 'array'],
            'court_count_total' => ['required', 'integer', 'min:0'],
            'courts' => ['nullable', 'array'],
            'courts.*.court_type_id' => ['required_with:courts', 'integer', 'exists:court_types,id'],
            'courts.*.name' => ['required_with:courts', 'string', 'max:100'],
        ]);

        $baseApplication = PartnerApplication::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['completed', 'contract_pending_sportgo_signature'])
            ->latest()
            ->first();

        $validated['business_name'] = $validated['business_name'] ?? $baseApplication?->business_name ?? $request->user()->full_name;
        $validated['tax_code'] = $validated['tax_code'] ?? $baseApplication?->tax_code;
        $validated['applicant_full_name'] = $baseApplication?->applicant_full_name ?? $request->user()->full_name;
        $validated['applicant_phone'] = $baseApplication?->applicant_phone ?? $request->user()->phone;
        $validated['applicant_email'] = $baseApplication?->applicant_email ?? $request->user()->email;
        $validated['representative_identity_number'] = $baseApplication?->representative_identity_number;
        $validated['bank_name'] = $baseApplication?->bank_name;
        $validated['bank_code'] = $baseApplication?->bank_code;
        $validated['account_number'] = $baseApplication?->account_number;
        $validated['account_holder_name'] = $baseApplication?->account_holder_name;
        $validated['bank_branch'] = $baseApplication?->bank_branch;

        $application = $this->partners->submitApplication($request->user(), $validated, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Yêu cầu đăng ký cụm sân mới đã được gửi.',
            'data' => $application,
        ], 201);
    }
}
