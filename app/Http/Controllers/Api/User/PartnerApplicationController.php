<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\CourtType;
use App\Models\GeneratedDocument;
use App\Models\PartnerApplication;
use App\Models\PartnerContract;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerBankService;
use App\Services\Partner\PartnerLocationService;
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
        private readonly PartnerLocationService $locations,
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
                'can_register' => $applications->whereNotIn('status', ['rejected', 'cancelled'])->isEmpty(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedApplicationData($request, true);
        $data = $this->enrichLocationNames($data);
        $data = $this->enrichBankVerification($data);
        $data['document_files'] = $this->documentFiles($request);

        $application = $this->partners->submitApplication($request->user(), $data, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gửi hồ sơ đăng ký đối tác.',
            'data' => $application,
        ], 201);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $this->validatedApplicationData($request, false);
        $data = $this->enrichLocationNames($data);
        $data = $this->enrichBankVerification($data);
        $data['attachments'] = $request->input('attachments_summary', 'Tài liệu sẽ được đính kèm khi gửi hồ sơ.');

        $document = $this->partners->previewApplicationForm($request->user(), $data);

        return response()->json([
            'status' => 'success',
            'data' => [
                'document' => $document,
                'download_url' => url('/api/files/documents/' . $document->id . '/download'),
                'preview' => $document->render_data,
                'bank_verification_status' => $data['bank_verification_status'],
            ],
        ]);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->banks->banks(),
        ]);
    }

    public function provinces(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->locations->provinces(),
        ]);
    }

    public function wards(string $provinceCode): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->locations->wards($provinceCode),
        ]);
    }

    public function verifyBankAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:50'],
            'bank_bin' => ['nullable', 'string', 'max:20'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
            'account_holder_name' => ['required', 'string', 'max:255'],
        ], $this->messages(), $this->attributes());

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
        ], $this->messages(), ['url' => 'link Google Maps']);

        if (! $this->isGoogleMapUrl($data['url'])) {
            throw ValidationException::withMessages([
                'url' => 'Vui lòng nhập link Google Maps hợp lệ của vị trí cụm sân.',
            ]);
        }

        $resolved = $this->maps->resolve($data['url']);
        if (! $resolved['latitude'] || ! $resolved['longitude']) {
            throw ValidationException::withMessages([
                'url' => 'Không lấy được tọa độ từ link Google Maps này. Vui lòng dùng link chia sẻ vị trí có tọa độ.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $resolved,
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ], $this->messages(), $this->attributes());

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
        ], $this->messages(), $this->attributes());

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
            ->orWhere(fn ($query) => $query
                ->where('owner_id', $request->user()->id)
                ->where('document_type', 'partner_application_form'))
            ->latest()
            ->get()
            ->map(fn (GeneratedDocument $document) => [
                ...$document->toArray(),
                'download_url' => url('/api/files/documents/' . $document->id . '/download'),
            ]);

        return response()->json(['status' => 'success', 'data' => $documents]);
    }

    private function validatedApplicationData(Request $request, bool $includeFiles): array
    {
        $this->normalizeStructuredPayload($request);

        $rules = [
            'applicant_full_name' => ['required', 'string', 'max:255'],
            'applicant_phone' => ['required', 'regex:/^0[0-9]{9}$/'],
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
            'tax_code' => ['nullable', 'regex:/^\d{10}(-?\d{3})?$/'],
            'business_code' => ['nullable', 'string', 'max:100'],
            'business_license_number' => ['required', 'string', 'max:100'],
            'business_address' => ['required', 'string', 'max:1000'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string', 'max:1000'],
            'venue_province_code' => ['required', 'string', 'max:20'],
            'venue_ward_code' => ['required', 'string', 'max:20'],
            'venue_map_url' => ['required', 'url', 'max:1000'],
            'venue_latitude' => ['required', 'numeric', 'between:-90,90'],
            'venue_longitude' => ['required', 'numeric', 'between:-180,180'],
            'venue_phone' => ['required', 'regex:/^0[0-9]{9}$/'],
            'venue_email' => ['nullable', 'email', 'max:255'],
            'venue_description' => ['nullable', 'string'],
            'expected_opening_hours' => ['nullable', 'string', 'max:255'],
            'parking_info' => ['nullable', 'string', 'max:1000'],
            'amenities' => ['nullable', 'array'],
            'court_count_total' => ['required', 'integer', 'min:1', 'max:100'],
            'base_price_per_hour' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'courts' => ['required', 'array', 'min:1'],
            'courts.*.court_type_id' => ['required_with:courts', 'integer', 'exists:court_types,id'],
            'courts.*.name' => ['required_with:courts', 'string', 'max:100'],
            'courts.*.note' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_code' => ['required', 'string', 'max:50'],
            'bank_bin' => ['nullable', 'string', 'max:20'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'confirmed' => [$includeFiles ? 'accepted' : 'nullable'],
        ];

        if ($includeFiles) {
            $rules += [
                'identity_documents' => ['required', 'array', 'min:1', 'max:5'],
                'identity_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
                'business_license_documents' => ['required', 'array', 'min:1', 'max:5'],
                'business_license_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
                'facility_images' => ['required', 'array', 'min:1', 'max:12'],
                'facility_images.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
                'additional_documents' => ['nullable', 'array', 'max:10'],
                'additional_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            ];
        } else {
            $rules['attachments_summary'] = ['nullable', 'string', 'max:1000'];
        }

        $data = $request->validate($rules, $this->messages(), $this->attributes());

        if (! $this->isGoogleMapUrl($data['venue_map_url'])) {
            throw ValidationException::withMessages([
                'venue_map_url' => 'Vui lòng nhập link Google Maps hợp lệ của vị trí cụm sân.',
            ]);
        }

        if (! $this->locations->assertWardBelongsToProvince($data['venue_province_code'], $data['venue_ward_code'])) {
            throw ValidationException::withMessages([
                'venue_ward_code' => 'Phường/Xã không thuộc Tỉnh/Thành phố đã chọn.',
            ]);
        }

        if ((int) $data['court_count_total'] !== count($data['courts'])) {
            throw ValidationException::withMessages([
                'court_count_total' => 'Số lượng sân con phải khớp với danh sách sân con đã nhập.',
            ]);
        }

        $this->assertUsableCourtTypes($data['courts']);

        return $data;
    }

    private function assertUsableCourtTypes(array $courts): void
    {
        $ids = collect($courts)->pluck('court_type_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $types = CourtType::query()
            ->withCount('children')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($courts as $index => $court) {
            $type = $types[(int) ($court['court_type_id'] ?? 0)] ?? null;
            if (! $type || ! $type->is_active || (int) $type->children_count > 0) {
                throw ValidationException::withMessages([
                    "courts.$index.court_type_id" => 'Vui lòng chọn loại sân con đang hoạt động và là loại sử dụng cuối.',
                ]);
            }
        }
    }

    private function enrichLocationNames(array $data): array
    {
        $province = $this->locations->provinceByCode($data['venue_province_code'] ?? null);
        $ward = $this->locations->wardByCode($data['venue_province_code'] ?? null, $data['venue_ward_code'] ?? null);

        $data['venue_province'] = $province['name'] ?? null;
        $data['venue_ward'] = $ward['name'] ?? null;
        $data['venue_district'] = null;

        return $data;
    }

    private function enrichBankVerification(array $data): array
    {
        $verification = $this->banks->verifyAccount(
            $data['bank_code'],
            $data['account_number'],
            $data['account_holder_name'],
            $data['bank_bin'] ?? null,
        );

        if (($verification['status'] ?? null) !== 'verified') {
            throw ValidationException::withMessages([
                'account_number' => $verification['message'] ?? 'Tài khoản ngân hàng chưa được xác minh tự động.',
            ]);
        }

        if ($bank = $verification['bank'] ?? $this->banks->findBank($data['bank_code'], $data['bank_bin'] ?? null)) {
            $data['bank_name'] = $bank['short_name'] ?: $bank['name'];
            $data['bank_code'] = $bank['code'];
        }

        $data['bank_verification_status'] = 'verified';

        return $data;
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

    private function isGoogleMapUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return str_contains($host, 'google.')
            || str_contains($host, 'goo.gl')
            || str_contains($host, 'maps.app.goo.gl');
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
            'additional' => $this->filesArray($request->file('additional_documents', [])),
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

    private function messages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'email' => ':attribute không đúng định dạng email.',
            'url' => ':attribute không đúng định dạng đường dẫn.',
            'regex' => ':attribute không đúng định dạng.',
            'integer' => ':attribute phải là số nguyên.',
            'numeric' => ':attribute phải là số.',
            'min' => ':attribute không hợp lệ.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'between' => ':attribute không nằm trong giới hạn hợp lệ.',
            'accepted' => 'Bạn cần xác nhận đã đọc đơn đăng ký trước khi gửi.',
            'mimes' => ':attribute chỉ hỗ trợ JPG, PNG, WEBP hoặc PDF.',
            'file' => ':attribute phải là file hợp lệ.',
            'exists' => ':attribute không tồn tại trong danh mục.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    private function attributes(): array
    {
        return [
            'applicant_full_name' => 'Họ tên người đăng ký',
            'applicant_phone' => 'Số điện thoại người đăng ký',
            'applicant_email' => 'Email người đăng ký',
            'applicant_address' => 'Địa chỉ liên hệ',
            'representative_name' => 'Người đại diện',
            'representative_identity_number' => 'Số CCCD/CMND/Hộ chiếu',
            'business_name' => 'Tên đơn vị kinh doanh',
            'business_license_number' => 'Số giấy đăng ký',
            'business_address' => 'Địa chỉ pháp lý',
            'venue_name' => 'Tên cụm sân',
            'venue_address' => 'Địa chỉ chi tiết cụm sân',
            'venue_province_code' => 'Tỉnh/Thành phố',
            'venue_ward_code' => 'Phường/Xã',
            'venue_map_url' => 'Link Google Maps',
            'venue_latitude' => 'Vĩ độ',
            'venue_longitude' => 'Kinh độ',
            'venue_phone' => 'Số điện thoại sân',
            'base_price_per_hour' => 'Giá cơ bản/giờ của cụm sân',
            'court_count_total' => 'Số lượng sân con',
            'courts' => 'Danh sách sân con',
            'courts.*.court_type_id' => 'Loại sân con',
            'courts.*.name' => 'Tên sân con',
            'bank_code' => 'Ngân hàng',
            'account_number' => 'Số tài khoản',
            'account_holder_name' => 'Tên chủ tài khoản',
            'identity_documents' => 'CCCD/CMND người đăng ký',
            'business_license_documents' => 'Giấy đăng ký kinh doanh/pháp lý',
            'facility_images' => 'Hình ảnh cơ sở/sân',
            'additional_documents' => 'Tài liệu bổ sung',
        ];
    }
}
