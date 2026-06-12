<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VenueCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));
        $perPage = min(max((int) $request->query('per_page', 12), 1), 50);

        $baseQuery = DB::table('vouchers')
            ->where('owner_type', 'venue')
            ->where('owner_id', $cluster->id);

        $summary = $this->summary((clone $baseQuery));

        $paginator = $baseQuery
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . trim((string) $request->query('keyword')) . '%';
                $query->where(fn ($inner) => $inner
                    ->where('code', 'like', $keyword)
                    ->orWhere('name', 'like', $keyword));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('discount_type'), fn ($query) => $query->where('discount_type', $request->query('discount_type')))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn ($voucher): array => $this->voucherPayload($voucher))
                ->values(),
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'venue_cluster' => $cluster,
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cluster = $this->ownedCluster($request, $request->query('venue_cluster_id'));
        $voucher = $this->venueVoucher($cluster->id, $id);

        return response()->json([
            'data' => [
                'voucher' => $this->voucherPayload($voucher),
                'usage_summary' => [
                    'total_quantity' => $voucher->total_quantity,
                    'used_quantity' => (int) $voucher->used_quantity,
                    'remaining_quantity' => $voucher->total_quantity === null ? null : max(0, (int) $voucher->total_quantity - (int) $voucher->used_quantity),
                    'usage_percent' => $this->usagePercent($voucher),
                ],
                'usages' => $this->voucherUsages($voucher->id),
                'audit_logs' => $this->auditLogs($voucher->id),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->voucherData($request);
        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);

        $voucherId = (string) Str::uuid();

        DB::transaction(function () use ($request, $data, $cluster, $voucherId): void {
            DB::table('vouchers')->insert([
                'id' => $voucherId,
                'code' => Str::upper($data['code']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_type' => 'venue',
                'owner_id' => $cluster->id,
                'funded_by' => 'venue',
                'stacking_rule' => $data['stacking_rule'] ?? 'exclusive',
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'min_order_amount' => $data['min_order_amount'] ?? 0,
                'total_quantity' => $data['total_quantity'] ?? null,
                'used_quantity' => 0,
                'per_user_limit' => $data['per_user_limit'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncScopes($voucherId, $cluster->id, $data['scopes'] ?? [['scope_type' => 'venue_cluster', 'scope_id' => $cluster->id]]);
        });

        $voucher = DB::table('vouchers')->where('id', $voucherId)->first();
        $this->audit($request, 'owner.voucher.created', 'vouchers', $voucherId, [], (array) $voucher);

        return response()->json([
            'message' => 'Đã tạo voucher của sân. Phần giảm giá do chủ sân chịu.',
            'data' => $this->voucherPayload($voucher),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->voucherData($request, $id);
        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $voucher = DB::table('vouchers')
            ->where('owner_type', 'venue')
            ->where('owner_id', $cluster->id)
            ->where('id', $id)
            ->first();

        if (! $voucher) {
            abort(404, 'Không tìm thấy voucher của cụm sân này.');
        }

        DB::transaction(function () use ($request, $data, $cluster, $id): void {
            DB::table('vouchers')->where('id', $id)->update([
                'code' => Str::upper($data['code']),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'max_discount_amount' => $data['max_discount_amount'] ?? null,
                'min_order_amount' => $data['min_order_amount'] ?? 0,
                'total_quantity' => $data['total_quantity'] ?? null,
                'per_user_limit' => $data['per_user_limit'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'updated_at' => now(),
            ]);

            DB::table('voucher_scopes')->where('voucher_id', $id)->delete();
            $this->syncScopes($id, $cluster->id, $data['scopes'] ?? [['scope_type' => 'venue_cluster', 'scope_id' => $cluster->id]]);
        });

        $fresh = DB::table('vouchers')->where('id', $id)->first();
        $this->audit($request, 'owner.voucher.updated', 'vouchers', $id, (array) $voucher, (array) $fresh);

        return response()->json([
            'message' => 'Đã cập nhật voucher của sân.',
            'data' => $this->voucherPayload($fresh),
        ]);
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $cluster = $this->ownedCluster($request, $data['venue_cluster_id']);
        $voucher = DB::table('vouchers')
            ->where('owner_type', 'venue')
            ->where('owner_id', $cluster->id)
            ->where('id', $id)
            ->first();

        if (! $voucher) {
            abort(404, 'Không tìm thấy voucher của cụm sân này.');
        }

        DB::table('vouchers')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);

        $this->audit($request, 'owner.voucher.deactivated', 'vouchers', $id, (array) $voucher, [
            'status' => 'inactive',
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['message' => 'Đã tắt voucher của sân.']);
    }

    private function voucherData(Request $request, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'string', 'exists:venue_clusters,id'],
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
            'scopes.*.scope_type' => ['required_with:scopes', Rule::in(['venue_cluster', 'court_type', 'booking_type'])],
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

    private function syncScopes(string $voucherId, string $clusterId, array $scopes): void
    {
        foreach ($scopes as $scope) {
            $scopeType = $scope['scope_type'] ?? 'venue_cluster';
            $scopeId = $scope['scope_id'] ?? null;

            if ($scopeType === 'venue_cluster') {
                $scopeId = $clusterId;
            }

            if ($scopeType === 'court_type') {
                $exists = DB::table('venue_courts')
                    ->where('venue_cluster_id', $clusterId)
                    ->where('court_type_id', $scopeId)
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        'scopes' => 'Loại sân của voucher không thuộc cụm sân này.',
                    ]);
                }
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

    private function voucherPayload(object $voucher): array
    {
        $scopes = DB::table('voucher_scopes')->where('voucher_id', $voucher->id)->get();

        return [
            ...(array) $voucher,
            'scopes' => $scopes,
            'type_label' => $this->discountTypeLabel($voucher->discount_type),
            'discount_type_label' => $this->discountTypeLabel($voucher->discount_type),
            'discount_label' => $voucher->discount_type === 'percent'
                ? rtrim(rtrim(number_format((float) $voucher->discount_value, 2, '.', ''), '0'), '.') . '%'
                : number_format((float) $voucher->discount_value, 0, ',', '.') . ' đ',
            'usage_percent' => $this->usagePercent($voucher),
            'remaining_quantity' => $voucher->total_quantity === null ? null : max(0, (int) $voucher->total_quantity - (int) $voucher->used_quantity),
            'status_label' => $this->statusLabel($voucher->status),
            'status_tone' => $this->statusTone($voucher->status),
            'scope_label' => $this->scopeLabel($scopes),
            'funding_label' => 'Voucher của sân - chủ sân chịu phần giảm giá',
        ];
    }

    private function venueVoucher(string $clusterId, string $id): object
    {
        $voucher = DB::table('vouchers')
            ->where('owner_type', 'venue')
            ->where('owner_id', $clusterId)
            ->where('id', $id)
            ->first();

        if (! $voucher) {
            abort(404, 'Không tìm thấy voucher của cụm sân này.');
        }

        return $voucher;
    }

    private function summary($query): array
    {
        $items = $query->get(['status', 'total_quantity', 'used_quantity', 'valid_to']);

        return [
            'total' => $items->count(),
            'active' => $items->where('status', 'active')->count(),
            'expiring_soon' => $items->filter(function ($voucher): bool {
                if ($voucher->status !== 'active' || ! $voucher->valid_to) {
                    return false;
                }

                $daysLeft = now()->diffInDays(\Illuminate\Support\Carbon::parse($voucher->valid_to), false);

                return $daysLeft >= 0 && $daysLeft <= 7;
            })->count(),
            'used_up' => $items->filter(fn ($voucher): bool => $voucher->total_quantity !== null && (int) $voucher->used_quantity >= (int) $voucher->total_quantity)->count(),
            'inactive' => $items->where('status', 'inactive')->count(),
        ];
    }

    private function usagePercent(object $voucher): int
    {
        if ($voucher->total_quantity === null || (int) $voucher->total_quantity <= 0) {
            return 0;
        }

        return min(100, (int) round(((int) $voucher->used_quantity / (int) $voucher->total_quantity) * 100));
    }

    private function discountTypeLabel(?string $type): string
    {
        return [
            'percent' => 'Giảm theo phần trăm',
            'fixed' => 'Giảm số tiền cố định',
        ][$type] ?? 'Không xác định';
    }

    private function statusTone(?string $status): string
    {
        return [
            'draft' => 'neutral',
            'active' => 'success',
            'inactive' => 'danger',
            'expired' => 'danger',
        ][$status] ?? 'neutral';
    }

    private function scopeLabel($scopes): string
    {
        if ($scopes->isEmpty()) {
            return 'Toàn cụm sân';
        }

        if ($scopes->contains('scope_type', 'venue_cluster')) {
            return 'Toàn cụm sân';
        }

        return $scopes
            ->groupBy('scope_type')
            ->map(fn ($items, $type): string => match ($type) {
                'court_type' => $items->count() . ' loại sân',
                'booking_type' => $items->count() . ' loại booking',
                default => $items->count() . ' phạm vi',
            })
            ->values()
            ->implode(', ');
    }

    private function voucherUsages(string $voucherId): array
    {
        if (! Schema::hasTable('voucher_usages')) {
            return [];
        }

        return DB::table('voucher_usages')
            ->leftJoin('users', 'users.id', '=', 'voucher_usages.user_id')
            ->where('voucher_usages.voucher_id', $voucherId)
            ->select(
                'voucher_usages.id',
                'voucher_usages.discount_amount',
                'voucher_usages.created_at',
                'users.full_name as user_name',
                'users.username as username'
            )
            ->latest('voucher_usages.created_at')
            ->limit(20)
            ->get()
            ->map(fn ($usage): array => [
                'id' => $usage->id,
                'user_name' => $usage->user_name ?: $usage->username ?: 'Khách hàng',
                'discount_amount' => (float) $usage->discount_amount,
                'created_at' => $usage->created_at,
            ])
            ->all();
    }

    private function auditLogs(string $voucherId): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return AuditLog::query()
            ->where('entity_type', 'vouchers')
            ->where('entity_id', $voucherId)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $this->auditActionLabel($log->action),
                'created_at' => $log->created_at,
                'technical_old_values' => $log->old_values,
                'technical_new_values' => $log->new_values,
            ])
            ->all();
    }

    private function auditActionLabel(?string $action): string
    {
        return [
            'owner.voucher.created' => 'Tạo voucher sân',
            'owner.voucher.updated' => 'Cập nhật voucher sân',
            'owner.voucher.deactivated' => 'Tắt voucher sân',
        ][$action] ?? 'Cập nhật voucher sân';
    }

    private function ownedCluster(Request $request, ?string $clusterId): VenueCluster
    {
        if (! $clusterId) {
            throw ValidationException::withMessages(['venue_cluster_id' => 'Vui lòng chọn cụm sân.']);
        }

        return VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->findOrFail($clusterId);
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

    private function audit(Request $request, string $action, string $entityType, string $entityId, array $oldValues, array $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'actor_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'context' => 'owner',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
