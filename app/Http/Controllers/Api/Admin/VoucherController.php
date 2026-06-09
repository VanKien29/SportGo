<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        $vouchers = DB::table('vouchers')
            ->where('owner_type', 'system')
            ->where('funded_by', 'system')
            ->when($request->filled('keyword'), function ($query) use ($request): void {
                $keyword = '%' . $request->query('keyword') . '%';
                $query->where(fn ($inner) => $inner
                    ->where('code', 'like', $keyword)
                    ->orWhere('name', 'like', $keyword));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('discount_type'), fn ($query) => $query->where('discount_type', $request->query('discount_type')))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($voucher): array => $this->payload($voucher));

        return response()->json(['data' => $vouchers]);
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
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncScopes($voucherId, $data['scopes'] ?? [['scope_type' => 'all', 'scope_id' => null]]);
        });

        $voucher = DB::table('vouchers')->where('id', $voucherId)->first();
        $this->audit($request, 'admin.system_voucher.created', 'vouchers', $voucherId, [], (array) $voucher);

        return response()->json([
            'message' => 'Đã tạo voucher hệ thống. Nền tảng chịu phần giảm giá.',
            'data' => $this->payload($voucher),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->voucherData($request, $id);
        $voucher = $this->systemVoucher($id);
        $used = (int) $voucher->used_quantity > 0;

        DB::transaction(function () use ($data, $id, $used): void {
            $update = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'valid_to' => $data['valid_to'] ?? null,
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
                    'valid_from' => $data['valid_from'] ?? null,
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
        ]);

        return response()->json([
            'message' => $used
                ? 'Voucher đã có lượt dùng nên chỉ cập nhật thông tin an toàn.'
                : 'Đã cập nhật voucher hệ thống.',
            'data' => $this->payload($fresh),
        ]);
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $voucher = $this->systemVoucher($id);

        DB::table('vouchers')->where('id', $id)->update([
            'status' => 'inactive',
            'updated_at' => now(),
        ]);

        $this->audit($request, 'admin.system_voucher.deactivated', 'vouchers', $id, (array) $voucher, [
            'status' => 'inactive',
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['message' => 'Đã tắt voucher hệ thống.']);
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

        return $data;
    }

    private function syncScopes(string $voucherId, array $scopes): void
    {
        foreach ($scopes as $scope) {
            $scopeType = $scope['scope_type'] ?? 'all';
            $scopeId = $scopeType === 'all' ? null : ($scope['scope_id'] ?? null);

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

    private function payload(object $voucher): array
    {
        $scopes = DB::table('voucher_scopes')->where('voucher_id', $voucher->id)->get();

        return [
            ...(array) $voucher,
            'scopes' => $scopes,
            'type_label' => $voucher->discount_type === 'percent' ? 'Giảm theo phần trăm' : 'Giảm số tiền cố định',
            'status_label' => $this->statusLabel($voucher->status),
            'funding_label' => 'Voucher hệ thống - nền tảng chịu phần giảm giá',
        ];
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
            'context' => 'admin',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
