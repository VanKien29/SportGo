<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerBankAccount;
use App\Models\PartnerApplication;
use App\Models\Role;
use App\Models\UserRole;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerApplicationController extends Controller
{
    private const REVIEWABLE_STATUSES = ['pending', 'reviewing'];
    private const ZERO_UUID = '00000000-0000-0000-0000-000000000000';

    public function index(Request $request): JsonResponse
    {
        $query = PartnerApplication::query()
            ->with([
                'user:id,full_name,username,email,phone',
                'reviewedBy:id,full_name,username,email',
                'approvedVenueCluster:id,name,status',
            ])
            ->withCount('courts');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('venue_name')) {
            $query->where('venue_name', 'like', '%' . $request->input('venue_name') . '%');
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

        $submittedFrom = $request->input('submitted_from', $request->input('date_from'));
        $submittedTo = $request->input('submitted_to', $request->input('date_to'));

        if ($submittedFrom) {
            $query->whereDate('submitted_at', '>=', $submittedFrom);
        }

        if ($submittedTo) {
            $query->whereDate('submitted_at', '<=', $submittedTo);
        }

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $applications = $query
            ->orderByDesc('submitted_at')
            ->paginate($perPage)
            ->through(fn (PartnerApplication $application) => $this->payload($application));

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $application = PartnerApplication::query()
            ->with([
                'user:id,full_name,username,email,phone,status',
                'reviewedBy:id,full_name,username,email',
                'approvedVenueCluster:id,name,status,slug,address',
                'courts.courtType:id,name',
                'bankAccounts',
                'contracts.signatures',
                'contracts.terminations',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->payload($application, includeDetail: true),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $application = PartnerApplication::query()
            ->with(['user', 'courts.courtType', 'bankAccounts'])
            ->findOrFail($id);

        if (! in_array($application->status, self::REVIEWABLE_STATUSES, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn này đã được xử lý, không thể duyệt lại.',
            ], 422);
        }

        $hasApplicationCourts = $application->courts->isNotEmpty();
        $data = $request->validate([
            'initial_court_name' => [Rule::requiredIf(! $hasApplicationCourts), 'nullable', 'string', 'max:100'],
            'court_type_id' => [Rule::requiredIf(! $hasApplicationCourts), 'nullable', 'integer', 'exists:court_types,id'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'account_holder_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_code' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'initial_court_name.required' => 'Vui lòng nhập tên sân con ban đầu.',
            'court_type_id.required' => 'Vui lòng chọn loại sân cho sân con ban đầu.',
            'court_type_id.exists' => 'Loại sân không hợp lệ.',
        ]);

        $bankPayload = $this->bankPayload($data);
        $actor = $request->user();

        $application = DB::transaction(function () use ($application, $data, $bankPayload, $actor) {
            $venueCluster = VenueCluster::create([
                'owner_id' => $application->user_id,
                'name' => $application->venue_name,
                'slug' => $this->uniqueVenueSlug($application->venue_name),
                'description' => $application->business_name,
                'phone_contact' => $application->user?->phone,
                'address' => $application->venue_address,
                'map_url' => $application->venue_map_url,
                'latitude' => $application->venue_latitude,
                'longitude' => $application->venue_longitude,
                'status' => 'pending_contract',
                'status_reason' => 'Chờ ký kết hợp đồng đối tác',
                'amenities' => $application->amenities,
            ]);

            if (is_array($application->amenities) && ! empty($application->amenities)) {
                $activeAmenities = \App\Models\Amenity::whereIn('name', $application->amenities)
                    ->where('status', 'active')
                    ->get();
                $syncData = [];
                foreach ($activeAmenities as $amenity) {
                    $syncData[$amenity->id] = [
                        'is_visible' => true,
                        'description' => null,
                    ];
                }
                $venueCluster->amenityCatalog()->sync($syncData);
            }

            $this->createVenueCourts($application, $venueCluster, $data);
            $this->activateExistingBankAccounts($application, $actor?->id);

            if ($bankPayload) {
                $this->storeBankAccount($application, $bankPayload, $actor?->id);
            }

            $application->forceFill([
                'status' => 'approved',
                'reviewed_by' => $actor?->id,
                'reviewed_at' => now(),
                'status_reason' => $data['review_note'] ?? null,
                'approved_venue_cluster_id' => $venueCluster->id,
            ])->save();

            // Generate contract
            $template = \App\Models\ContractTemplate::first();
            if ($template) {
                $contractService = app(\App\Services\Partner\ContractGenerationService::class);
                $contract = $contractService->generate($application->id, $template->id);
                $contractService->sendEmail($contract);
            }

            \App\Models\Notification::create([
                'user_id' => $application->user_id,
                'type' => 'partner_application_approved',
                'title' => 'Hồ sơ đối tác đã được duyệt',
                'body' => 'Hồ sơ đăng ký đối tác của bạn đã được duyệt thành công. Hợp đồng đã được tạo và gửi cho bạn, vui lòng kiểm tra và ký kết.',
                'reference_type' => 'partner_application',
                'reference_id' => $application->id,
            ]);

            return $application->fresh([
                'user:id,full_name,username,email,phone,status',
                'reviewedBy:id,full_name,username,email',
                'approvedVenueCluster:id,name,status,slug,address',
                'courts.courtType:id,name',
                'bankAccounts',
                'contracts.signatures',
                'contracts.terminations',
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Duyệt đơn đăng kí thành công.',
            'data' => $this->payload($application, includeDetail: true),
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $application = PartnerApplication::query()
            ->with(['user', 'reviewedBy'])
            ->findOrFail($id);

        if (! in_array($application->status, self::REVIEWABLE_STATUSES, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đơn này đã được xử lý, không thể từ chối lại.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'status_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $reason = trim((string) ($data['reason'] ?? $data['status_reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Vui lòng nhập lý do từ chối.',
            ]);
        }

        $application->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $request->user()?->id,
            'status_reason' => $reason,
            'reviewed_at' => now(),
        ])->save();

        \App\Models\Notification::create([
            'user_id' => $application->user_id,
            'type' => 'partner_application_rejected',
            'title' => 'Hồ sơ đối tác bị từ chối',
            'body' => 'Hồ sơ đăng ký đối tác của bạn đã bị từ chối. Lý do: ' . $reason,
            'reference_type' => 'partner_application',
            'reference_id' => $application->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Từ chối đơn đăng kí thành công.',
            'data' => $this->payload($application->fresh(['user', 'reviewedBy']), includeDetail: true),
        ]);
    }

    private function createVenueCourts(PartnerApplication $application, VenueCluster $venueCluster, array $data): void
    {
        $courts = $application->courts;

        if ($courts->isEmpty()) {
            VenueCourt::create([
                'venue_cluster_id' => $venueCluster->id,
                'court_type_id' => $data['court_type_id'],
                'name' => $data['initial_court_name'],
                'status' => 'active',
                'sort_order' => 1,
            ]);

            return;
        }

        foreach ($courts as $index => $court) {
            VenueCourt::create([
                'venue_cluster_id' => $venueCluster->id,
                'court_type_id' => $court->court_type_id,
                'name' => $court->name,
                'status' => 'active',
                'sort_order' => $court->sort_order ?: ($index + 1),
            ]);
        }
    }

    private function grantVenueOwnerRole(string $userId, ?string $actorId): void
    {
        $role = Role::query()->where('name', 'venue_owner')->first();

        if (! $role) {
            return;
        }

        UserRole::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'role_id' => $role->id,
                'scope_type' => 'system',
                'scope_id' => self::ZERO_UUID,
            ],
            [
                'granted_by' => $actorId,
            ]
        );
    }

    private function activateExistingBankAccounts(PartnerApplication $application, ?string $actorId): void
    {
        $application->bankAccounts()->update([
            'status' => 'active',
            'verified_by' => $actorId,
            'verified_at' => now(),
            'rejected_reason' => null,
        ]);
    }

    private function storeBankAccount(PartnerApplication $application, array $bankPayload, ?string $actorId): void
    {
        OwnerBankAccount::query()
            ->where('owner_id', $application->user_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        OwnerBankAccount::query()->updateOrCreate(
            [
                'owner_id' => $application->user_id,
                'bank_code' => $bankPayload['bank_code'],
                'account_number' => $bankPayload['account_number'],
            ],
            [
                'partner_application_id' => $application->id,
                'bank_name' => $bankPayload['bank_name'],
                'account_holder_name' => $bankPayload['account_holder_name'],
                'branch_name' => $bankPayload['branch_name'],
                'status' => 'active',
                'is_default' => true,
                'verified_by' => $actorId,
                'verified_at' => now(),
                'rejected_reason' => null,
            ]
        );
    }

    private function bankPayload(array $data): ?array
    {
        $holder = $data['bank_account_name'] ?? $data['account_holder_name'] ?? null;
        $number = $data['bank_account_number'] ?? $data['account_number'] ?? null;
        $bankName = $data['bank_name'] ?? null;
        $branchName = $data['branch_name'] ?? null;

        if (! $holder && ! $number && ! $bankName && ! ($data['bank_code'] ?? null)) {
            return null;
        }

        if (! $holder || ! $number || ! $bankName) {
            throw ValidationException::withMessages([
                'bank_account' => 'Vui lòng nhập đủ tên ngân hàng, số tài khoản và tên chủ tài khoản.',
            ]);
        }

        $bankCode = $data['bank_code'] ?? Str::upper(Str::slug($bankName, '_'));

        return [
            'bank_name' => $bankName,
            'bank_code' => $bankCode ?: 'N_A',
            'account_number' => $number,
            'account_holder_name' => $holder,
            'branch_name' => $branchName,
        ];
    }

    private function uniqueVenueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue-cluster';
        $slug = $base;
        $suffix = 2;

        while (VenueCluster::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function payload(PartnerApplication $application, bool $includeDetail = false): array
    {
        $reviewedBy = $application->reviewedBy ? [
            'id' => $application->reviewedBy->id,
            'full_name' => $application->reviewedBy->full_name,
            'username' => $application->reviewedBy->username,
            'email' => $application->reviewedBy->email,
        ] : null;

        $payload = [
            'id' => $application->id,
            'user_id' => $application->user_id,
            'type' => $application->type,
            'business_name' => $application->business_name,
            'tax_code' => $application->tax_code,
            'venue_name' => $application->venue_name,
            'venue_address' => $application->venue_address,
            'venue_map_url' => $application->venue_map_url,
            'venue_latitude' => $application->venue_latitude,
            'venue_longitude' => $application->venue_longitude,
            'venue_description' => $application->venue_description,
            'amenities' => $application->amenities,
            'status' => $application->status,
            'status_reason' => $application->status_reason,
            'approved_venue_cluster_id' => $application->approved_venue_cluster_id,
            'submitted_at' => $application->submitted_at,
            'reviewed_at' => $application->reviewed_at,
            'created_at' => $application->created_at,
            'updated_at' => $application->updated_at,
            'courts_count' => $application->courts_count ?? $application->courts?->count() ?? 0,
            'user' => $application->user ? [
                'id' => $application->user->id,
                'full_name' => $application->user->full_name,
                'username' => $application->user->username,
                'email' => $application->user->email,
                'phone' => $application->user->phone,
                'status' => $application->user->status,
            ] : null,
            'reviewed_by' => $reviewedBy,
            'reviewedBy' => $reviewedBy,
            'approved_venue_cluster' => $application->approvedVenueCluster ? [
                'id' => $application->approvedVenueCluster->id,
                'name' => $application->approvedVenueCluster->name,
                'slug' => $application->approvedVenueCluster->slug,
                'status' => $application->approvedVenueCluster->status,
                'address' => $application->approvedVenueCluster->address,
            ] : null,
        ];

        if (! $includeDetail) {
            return $payload;
        }

        $payload['courts'] = $application->courts->map(fn ($court) => [
            'id' => $court->id,
            'name' => $court->name,
            'sort_order' => $court->sort_order,
            'court_type_id' => $court->court_type_id,
            'court_type' => $court->courtType ? [
                'id' => $court->courtType->id,
                'name' => $court->courtType->name,
            ] : null,
        ])->values();

        $payload['bank_accounts'] = $application->bankAccounts->map(fn ($account) => [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'account_number' => $account->account_number,
            'account_holder_name' => $account->account_holder_name,
            'branch_name' => $account->branch_name,
            'status' => $account->status,
            'is_default' => (bool) $account->is_default,
        ])->values();

        $payload['user_info'] = $payload['user'];
        $payload['business_info'] = [
            'business_name' => $application->business_name,
            'tax_code' => $application->tax_code,
            'venue_name' => $application->venue_name,
        ];
        $payload['venue_info'] = [
            'address' => $application->venue_address,
            'map_url' => $application->venue_map_url,
            'latitude' => $application->venue_latitude,
            'longitude' => $application->venue_longitude,
        ];
        $payload['review_info'] = [
            'status' => $application->status,
            'reviewed_by' => $reviewedBy,
            'status_reason' => $application->status_reason,
            'reviewed_at' => $application->reviewed_at,
        ];

        // Include contracts for Admin UI
        if ($application->relationLoaded('contracts')) {
            $payload['contracts'] = $application->contracts;
        }

        return $payload;
    }
}
