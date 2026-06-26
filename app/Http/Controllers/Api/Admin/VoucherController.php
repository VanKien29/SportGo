<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 50);

        $baseQuery = DB::table('vouchers')
            ->where('owner_type', 'system')
            ->where('funded_by', 'system')
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . trim((string) $request->query('keyword')) . '%';
                $query->where(fn ($inner) => $inner
                    ->where('code', 'like', $keyword)
                    ->orWhere('name', 'like', $keyword));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('discount_type'), fn ($query) => $query->where('discount_type', $request->query('discount_type')));

        $paginator = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn ($voucher): array => $this->listPayload($voucher))
                ->values(),
            'summary' => $this->summary(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $voucher = $this->systemVoucher($id);

        return response()->json([
            'data' => $this->detailPayload($voucher),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->voucherData($request);
        $voucherId = (string) Str::uuid();

        DB::transaction(function () use ($request, $data, $voucherId): void {
            DB::table('vouchers')->insert([
                'id' => $voucherId,
                'code' => Str::upper($data['code']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_type' => 'system',
                'owner_id' => null,
                'funded_by' => 'system',
                'stacking_rule' => $data['stacking_rule'] ?? 'exclusive',
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'min_order_amount' => $data['min_order_amount'] ?? 0,
                'total_quantity' => $data['total_quantity'] ?? null,
                'used_quantity' => 0,
                'per_user_limit' => $data['per_user_limit'] ?? null,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'status' => $data['status'] ?? 'draft',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncScopes($voucherId, $data['scopes'] ?? [['scope_type' => 'all', 'scope_id' => null]]);
        });

        $voucher = DB::table('vouchers')->where('id', $voucherId)->first();
        $this->audit($request, 'admin.system_voucher.created', 'vouchers', $voucherId, [], (array) $voucher, 'Tạo voucher hệ thống.');

        return response()->json([
            'message' => 'Đã tạo voucher hệ thống.',
            'data' => $this->detailPayload($voucher),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->voucherData($request, $id);
        $voucher = $this->systemVoucher($id);
        $used = (int) $voucher->used_quantity > 0 || DB::table('voucher_usages')->where('voucher_id', $id)->exists();

        $finalValidFrom = $used ? $voucher->valid_from : $data['valid_from'];
        if ($data['valid_to'] && $finalValidFrom && strtotime($data['valid_to']) <= strtotime($finalValidFrom)) {
            throw ValidationException::withMessages([
                'valid_to' => 'Thời gian kết thúc phải sau thời gian bắt đầu của voucher.',
            ]);
        }

        DB::transaction(function () use ($data, $id, $used): void {
            $update = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'valid_to' => $data['valid_to'],
                'updated_at' => now(),
            ];

            if (! $used) {
                $update = [
                    ...$update,
                    'code' => Str::upper($data['code']),
                    'discount_type' => $data['discount_type'],
                    'discount_value' => $data['discount_value'],
                    'max_discount_amount' => $data['max_discount_amount'] ?? null,
                    'min_order_amount' => $data['min_order_amount'] ?? 0,
                    'total_quantity' => $data['total_quantity'] ?? null,
                    'per_user_limit' => $data['per_user_limit'] ?? null,
                    'valid_from' => $data['valid_from'],
                ];

                DB::table('voucher_scopes')->where('voucher_id', $id)->delete();
                $this->syncScopes($id, $data['scopes'] ?? [['scope_type' => 'all', 'scope_id' => null]]);
            }

            DB::table('vouchers')->where('id', $id)->update($update);
        });

        $fresh = DB::table('vouchers')->where('id', $id)->first();
        $this->audit($request, 'admin.system_voucher.updated', 'vouchers', $id, (array) $voucher, [
            ...(array) $fresh,
            'locked_financial_fields' => $used,
        ], 'Cập nhật voucher hệ thống.');

        return response()->json([
            'message' => $used
                ? 'Voucher đã có lượt dùng nên chỉ cập nhật thông tin an toàn.'
                : 'Đã cập nhật voucher hệ thống.',
            'data' => $this->detailPayload($fresh),
        ]);
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do tắt voucher.',
        ]);

        $voucher = $this->systemVoucher($id);

        DB::table('vouchers')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);

        $this->audit($request, 'admin.system_voucher.deactivated', 'vouchers', $id, (array) $voucher, [
            'status' => 'inactive',
            'reason' => $data['reason'],
        ], $data['reason']);

        return response()->json(['message' => 'Đã tắt voucher hệ thống.']);
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $voucher = $this->systemVoucher($id);
        $this->assertVoucherCanBeActivated($voucher);

        DB::table('vouchers')->where('id', $id)->update([
            'status' => 'active',
            'updated_at' => now(),
        ]);

        $this->audit($request, 'admin.system_voucher.activated', 'vouchers', $id, (array) $voucher, [
            'status' => 'active',
        ], 'Kích hoạt lại voucher hệ thống.');

        return response()->json(['message' => 'Đã kích hoạt voucher hệ thống.']);
    }

    private function voucherData(Request $request, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'total_quantity' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after:valid_from'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'expired'])],
            'scopes' => ['array'],
            'scopes.*.scope_type' => ['required_with:scopes', Rule::in(['all', 'venue_cluster', 'court_type', 'booking_type'])],
            'scopes.*.scope_id' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Voucher phần trăm phải nằm trong khoảng 1 đến 100.',
            ]);
        }

        if (($data['total_quantity'] ?? null) !== null && ($data['per_user_limit'] ?? null) !== null && $data['per_user_limit'] > $data['total_quantity']) {
            throw ValidationException::withMessages([
                'per_user_limit' => 'Giới hạn mỗi khách không được lớn hơn tổng số lượng voucher.',
            ]);
        }

        return $data;
    }

    private function syncScopes(string $voucherId, array $scopes): void
    {
        $scopes = count($scopes) > 0 ? $scopes : [['scope_type' => 'all', 'scope_id' => null]];

        foreach ($scopes as $scope) {
            $scopeType = $scope['scope_type'] ?? 'all';
            $scopeId = $scopeType === 'all' ? null : ($scope['scope_id'] ?? null);

            if ($scopeType !== 'all' && blank($scopeId)) {
                throw ValidationException::withMessages([
                    'scopes' => 'Phạm vi cụ thể phải có mã phạm vi.',
                ]);
            }

            DB::table('voucher_scopes')->insert([
                'id' => (string) Str::uuid(),
                'voucher_id' => $voucherId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_key' => $scopeType . ':' . ($scopeId ?: 'all'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function systemVoucher(string $id): object
    {
        $voucher = DB::table('vouchers')
            ->where('owner_type', 'system')
            ->where('funded_by', 'system')
            ->where('id', $id)
            ->first();

        if (! $voucher) {
            abort(404, 'Không tìm thấy voucher hệ thống.');
        }

        return $voucher;
    }

    private function listPayload(object $voucher): array
    {
        $scopes = DB::table('voucher_scopes')->where('voucher_id', $voucher->id)->get();

        return [
            'id' => $voucher->id,
            'code' => $voucher->code,
            'name' => $voucher->name,
            'discount_type' => $voucher->discount_type,
            'discount_type_label' => $this->discountTypeLabel($voucher->discount_type),
            'discount_value' => $voucher->discount_value,
            'discount_label' => $this->discountLabel($voucher),
            'max_discount_amount' => $voucher->max_discount_amount,
            'min_order_amount' => $voucher->min_order_amount,
            'total_quantity' => $voucher->total_quantity,
            'used_quantity' => $voucher->used_quantity,
            'remaining_quantity' => $this->remainingQuantity($voucher),
            'usage_percent' => $this->usagePercent($voucher),
            'valid_from' => $voucher->valid_from,
            'valid_to' => $voucher->valid_to,
            'status' => $voucher->status,
            'status_label' => $this->statusLabel($voucher->status),
            'status_tone' => $this->statusTone($voucher->status),
            'scope_label' => $this->scopeSummary($scopes),
            'funding_label' => 'Voucher hệ thống - nền tảng chịu phần giảm giá',
            'actions_allowed' => [
                'view' => true,
                'edit' => true,
                'deactivate' => $voucher->status !== 'inactive',
                'activate' => in_array($voucher->status, ['draft', 'inactive'], true),
            ],
        ];
    }

    private function detailPayload(object $voucher): array
    {
        $scopes = DB::table('voucher_scopes')->where('voucher_id', $voucher->id)->orderBy('scope_type')->get();
        $usages = $this->voucherUsages($voucher->id);
        $audits = $this->voucherAudits($voucher->id);

        return [
            'summary' => $this->listPayload($voucher),
            'voucher' => [
                ...(array) $voucher,
                'status_label' => $this->statusLabel($voucher->status),
                'discount_type_label' => $this->discountTypeLabel($voucher->discount_type),
                'discount_label' => $this->discountLabel($voucher),
                'remaining_quantity' => $this->remainingQuantity($voucher),
                'funding_label' => 'Voucher hệ thống - nền tảng chịu phần giảm giá',
            ],
            'conditions' => [
                'min_order_amount' => $voucher->min_order_amount,
                'max_discount_amount' => $voucher->max_discount_amount,
                'per_user_limit' => $voucher->per_user_limit,
                'stacking_rule' => $voucher->stacking_rule,
                'stacking_rule_label' => $this->stackingRuleLabel($voucher->stacking_rule),
                'valid_from' => $voucher->valid_from,
                'valid_to' => $voucher->valid_to,
            ],
            'scopes' => $scopes->map(fn ($scope): array => [
                'id' => $scope->id,
                'scope_type' => $scope->scope_type,
                'scope_type_label' => $this->scopeTypeLabel($scope->scope_type),
                'scope_id' => $scope->scope_id,
                'scope_key' => $scope->scope_key,
                'display_label' => $this->scopeItemLabel($scope),
            ])->values(),
            'usage_summary' => [
                'total_quantity' => $voucher->total_quantity,
                'used_quantity' => $voucher->used_quantity,
                'remaining_quantity' => $this->remainingQuantity($voucher),
                'usage_records' => $usages->count(),
                'total_discount_amount' => (float) $usages->sum('discount_amount'),
            ],
            'usages' => $usages,
            'audit_logs' => $audits,
        ];
    }

    private function voucherUsages(string $voucherId): Collection
    {
        if (! Schema::hasTable('voucher_usages')) {
            return collect();
        }

        return DB::table('voucher_usages')
            ->leftJoin('users', 'users.id', '=', 'voucher_usages.user_id')
            ->leftJoin('bookings', 'bookings.id', '=', 'voucher_usages.booking_id')
            ->where('voucher_usages.voucher_id', $voucherId)
            ->orderByDesc('voucher_usages.created_at')
            ->limit(20)
            ->get([
                'voucher_usages.id',
                'voucher_usages.discount_amount',
                'voucher_usages.status',
                'voucher_usages.used_at',
                'voucher_usages.created_at',
                'users.full_name as user_name',
                'users.email as user_email',
                'bookings.booking_code',
                'bookings.status as booking_status',
            ])
            ->map(fn ($usage): array => [
                'id' => $usage->id,
                'user_name' => $usage->user_name,
                'user_email' => $usage->user_email,
                'booking_code' => $usage->booking_code,
                'booking_status' => $usage->booking_status,
                'booking_status_label' => $this->bookingStatusLabel($usage->booking_status),
                'discount_amount' => $usage->discount_amount,
                'status' => $usage->status,
                'status_label' => $this->usageStatusLabel($usage->status),
                'used_at' => $usage->used_at,
                'created_at' => $usage->created_at,
            ]);
    }

    private function voucherAudits(string $voucherId): Collection
    {
        if (! Schema::hasTable('audit_logs')) {
            return collect();
        }

        return AuditLog::query()
            ->with('actor:id,full_name,username,email')
            ->where('entity_type', 'vouchers')
            ->where('entity_id', $voucherId)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $this->auditActionLabel($log->action),
                'actor_name' => $log->actor?->full_name ?: $log->actor?->username,
                'reason' => $log->reason,
                'created_at' => $log->created_at,
                'summary' => $this->auditActionLabel($log->action) . ($log->reason ? ': ' . $log->reason : ''),
                'technical_old_values' => $log->old_values,
                'technical_new_values' => $log->new_values,
            ]);
    }

    private function assertVoucherCanBeActivated(object $voucher): void
    {
        if ($voucher->valid_to && now()->greaterThan($voucher->valid_to)) {
            throw ValidationException::withMessages([
                'status' => 'Voucher đã hết hạn, vui lòng chỉnh thời gian hiệu lực trước khi kích hoạt.',
            ]);
        }

        if ($voucher->total_quantity !== null && (int) $voucher->used_quantity >= (int) $voucher->total_quantity) {
            throw ValidationException::withMessages([
                'status' => 'Voucher đã dùng hết số lượng, không thể kích hoạt lại.',
            ]);
        }
    }

    private function remainingQuantity(object $voucher): ?int
    {
        if ($voucher->total_quantity === null) {
            return null;
        }

        return max((int) $voucher->total_quantity - (int) $voucher->used_quantity, 0);
    }

    private function usagePercent(object $voucher): int
    {
        if ($voucher->total_quantity === null || (int) $voucher->total_quantity <= 0) {
            return 0;
        }

        return min(100, (int) round(((int) $voucher->used_quantity / (int) $voucher->total_quantity) * 100));
    }

    private function summary(): array
    {
        $query = DB::table('vouchers')
            ->where('owner_type', 'system')
            ->where('funded_by', 'system');

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'expiring_soon' => (clone $query)
                ->where('status', 'active')
                ->whereBetween('valid_to', [now(), now()->addDays(7)])
                ->count(),
            'used_up' => (clone $query)
                ->whereNotNull('total_quantity')
                ->whereColumn('used_quantity', '>=', 'total_quantity')
                ->count(),
            'inactive' => (clone $query)->where('status', 'inactive')->count(),
        ];
    }

    private function discountLabel(object $voucher): string
    {
        if ($voucher->discount_type === 'percent') {
            return rtrim(rtrim((string) $voucher->discount_value, '0'), '.') . '%';
        }

        return number_format((float) $voucher->discount_value, 0, ',', '.') . ' đ';
    }

    private function discountTypeLabel(?string $type): string
    {
        return [
            'percent' => 'Giảm theo phần trăm',
            'fixed' => 'Giảm số tiền cố định',
        ][$type] ?? 'Không xác định';
    }

    private function statusLabel(?string $status): string
    {
        return [
            'draft' => 'Bản nháp',
            'active' => 'Đang áp dụng',
            'inactive' => 'Đã tắt',
            'expired' => 'Hết hạn',
        ][$status] ?? 'Không xác định';
    }

    private function statusTone(?string $status): string
    {
        return [
            'draft' => 'neutral',
            'active' => 'success',
            'inactive' => 'danger',
            'expired' => 'warning',
        ][$status] ?? 'neutral';
    }

    private function stackingRuleLabel(?string $rule): string
    {
        return [
            'exclusive' => 'Không dùng chung với voucher khác',
            'allow_with_system' => 'Có thể dùng chung với voucher hệ thống khác',
            'allow_with_venue' => 'Có thể dùng chung với voucher của sân',
        ][$rule] ?? 'Không xác định';
    }

    private function scopeSummary(Collection $scopes): string
    {
        if ($scopes->isEmpty() || $scopes->contains(fn ($scope) => $scope->scope_type === 'all')) {
            return 'Toàn hệ thống';
        }

        return $scopes
            ->groupBy('scope_type')
            ->map(fn ($items, $type) => $this->scopeTypeLabel($type) . ': ' . $items->count())
            ->implode(', ');
    }

    private function scopeTypeLabel(?string $type): string
    {
        return [
            'all' => 'Toàn hệ thống',
            'venue_cluster' => 'Cụm sân',
            'court_type' => 'Loại sân',
            'booking_type' => 'Loại booking',
        ][$type] ?? 'Phạm vi khác';
    }

    private function scopeItemLabel(object $scope): string
    {
        if ($scope->scope_type === 'all') {
            return 'Toàn hệ thống';
        }

        return $this->scopeTypeLabel($scope->scope_type) . ($scope->scope_id ? ': ' . $scope->scope_id : '');
    }

    private function usageStatusLabel(?string $status): string
    {
        return [
            'applied' => 'Đã áp dụng',
            'cancelled' => 'Đã hủy',
            'refunded' => 'Đã hoàn',
        ][$status] ?? 'Không xác định';
    }

    private function bookingStatusLabel(?string $status): string
    {
        return [
            'pending_approval' => 'Chờ duyệt',
            'pending_payment' => 'Chờ thanh toán',
            'confirmed' => 'Đã xác nhận',
            'checked_in' => 'Đã check-in',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
            'expired' => 'Hết hạn',
            'rejected' => 'Bị từ chối',
        ][$status] ?? 'Không xác định';
    }

    private function auditActionLabel(?string $action): string
    {
        return [
            'admin.system_voucher.created' => 'Tạo voucher hệ thống',
            'admin.system_voucher.updated' => 'Cập nhật voucher hệ thống',
            'admin.system_voucher.deactivated' => 'Tắt voucher hệ thống',
            'admin.system_voucher.activated' => 'Kích hoạt voucher hệ thống',
        ][$action] ?? 'Thay đổi voucher';
    }

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $oldValues, array $newValues, ?string $reason = null): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'actor_type' => 'user',
            'module' => 'voucher',
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'admin',
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}