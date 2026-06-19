<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerBankService;
use App\Services\Partner\PartnerMapResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly PartnerApplicationService $partners,
        private readonly PartnerBankService $banks,
        private readonly PartnerMapResolver $maps,
    ) {
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
                'can_register' => $applications
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->isEmpty(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeStructuredPayload($request);

        $data = $request->validate([
            'applicant_full_name' => ['required', 'string', 'max:255'],
            'applicant_phone' => ['required', 'regex:/^(0|\+84)[0-9\s\.\-]{8,14}$/'],
            'applicant_email' => ['required', 'email', 'max:255'],
            'applicant_address' => ['required', 'string', 'max:1000'],
            'applicant_type' => ['required', 'in:individual,business,company'],
            'representative_name' => ['required', 'string', 'max:255'],
            'representative_identity_type' => ['required', 'in:cccd,cmnd,passport'],
            'representative_identity_number' => ['required', 'string', 'max:50'],
            'representative_identity_issued_date' => ['nullable', 'date'],
            'representative_identity_issued_place' => ['nullable', 'string', 'max:255'],
            'representative_position' => ['nullable', 'string', 'max:150'],
            'business_name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:50'],
            'business_code' => ['nullable', 'string', 'max:100'],
            'business_license_number' => ['required', 'string', 'max:100'],
            'business_address' => ['required', 'string', 'max:1000'],
            'business_representative_name' => ['nullable', 'string', 'max:255'],
            'business_representative_position' => ['nullable', 'string', 'max:150'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string', 'max:1000'],
            'venue_province' => ['required', 'string', 'max:100'],
            'venue_district' => ['nullable', 'string', 'max:100'],
            'venue_ward' => ['required', 'string', 'max:100'],
            'venue_map_url' => ['required', 'url', 'max:1000'],
            'venue_latitude' => ['required', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['required', 'numeric', 'between:-180,180'],
            'venue_phone' => ['required', 'regex:/^(0|\+84)[0-9\s\.\-]{8,14}$/'],
            'venue_email' => ['nullable', 'email', 'max:255'],
            'venue_description' => ['nullable', 'string'],
            'expected_opening_hours' => ['nullable', 'string', 'max:255'],
            'parking_info' => ['nullable', 'string', 'max:1000'],
            'amenities' => ['nullable', 'array'],
            'court_count_total' => ['required', 'integer', 'min:1', 'max:100'],
            'courts' => ['required', 'array', 'min:1'],
            'courts.*.court_type_id' => ['required_with:courts', 'integer', 'exists:court_types,id'],
            'courts.*.name' => ['required_with:courts', 'string', 'max:100'],
            'courts.*.base_price' => ['required_with:courts', 'numeric', 'min:0', 'max:100000000'],
            'courts.*.note' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_code' => ['required', 'string', 'max:50'],
            'bank_bin' => ['nullable', 'string', 'max:20'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'identity_documents' => ['required', 'array', 'min:1', 'max:5'],
            'identity_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            'business_license_documents' => ['required', 'array', 'min:1', 'max:5'],
            'business_license_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            'facility_images' => ['required', 'array', 'min:1', 'max:12'],
            'facility_images.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            'confirmed' => ['accepted'],
        ]);

        $verification = $this->banks->verifyAccount(
            $data['bank_code'],
            $data['account_number'],
            $data['account_holder_name'],
            $data['bank_bin'] ?? null,
        );

        if (in_array($verification['status'], ['invalid_bank', 'invalid_account_number', 'not_found', 'name_mismatch'], true)) {
            throw ValidationException::withMessages([
                'account_number' => $verification['message'],
            ]);
        }

        if ($bank = $verification['bank'] ?? $this->banks->findBank($data['bank_code'], $data['bank_bin'] ?? null)) {
            $data['bank_name'] = $bank['short_name'] ?: $bank['name'];
            $data['bank_code'] = $bank['code'];
        }

        $data['bank_verification_status'] = ($verification['status'] ?? null) === 'verified' ? 'verified' : 'pending';
        $data['document_files'] = $this->documentFiles($request);

        $application = $this->partners->submitApplication($request->user(), $data, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi hồ sơ đăng ký đối tác.',
            'data' => $application,
        ], 201);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->banks->banks(),
        ]);
    }

    public function verifyBankAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:50'],
            'bank_bin' => ['nullable', 'string', 'max:20'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
            'account_holder_name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->banks->verifyAccount(
                $data['bank_code'],
                $data['account_number'],
                $data['account_holder_name'],
                $data['bank_bin'] ?? null,
            ),
        ]);
    }

    public function resolveMap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:1000'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->maps->resolve($data['url']),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $application = PartnerApplication::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã hủy hồ sơ đăng ký đối tác.',
            'data' => $this->partners->cancelApplication($application, $request->user(), $data['reason'] ?? null, $request),
        ]);
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

    private function normalizeStructuredPayload(Request $request): void
    {
        foreach (['courts', 'amenities'] as $key) {
            $value = $request->input($key);
            if (! is_string($value)) {
                continue;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge([$key => $decoded]);
            }
        }
    }

    /**
     * @return array<string, array<int, UploadedFile>>
     */
    private function documentFiles(Request $request): array
    {
        return [
            'identity' => $this->filesArray($request->file('identity_documents', [])),
            'business_license' => $this->filesArray($request->file('business_license_documents', [])),
            'facility' => $this->filesArray($request->file('facility_images', [])),
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function filesArray(mixed $files): array
    {
        return collect(Arr::wrap($files))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values()
            ->all();
    }
}
