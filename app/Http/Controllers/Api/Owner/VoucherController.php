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

        $vouchers = DB::table('vouchers')
            ->where('owner_type', 'venue')
            ->where('owner_id', $cluster->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($voucher): array => $this->voucherPayload($voucher));

        return response()->json(['data' => $vouchers]);
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
            'type_label' => $voucher->discount_type === 'percent' ? 'Giảm theo phần trăm' : 'Giảm số tiền cố định',
            'status_label' => $this->statusLabel($voucher->status),
            'funding_label' => 'Voucher của sân - chủ sân chịu phần giảm giá',
        ];
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
