<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeTier;
use App\Models\SystemPolicy;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformFeeTierController extends Controller
{
    private const SETTINGS_KEY = 'platform_fee_settings';

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'keyword' => ['nullable', 'string', 'max:100'],
        ]);

        $tiers = PlatformFeeTier::query()
            ->withCount(['ledgers as usage_count'])
            ->when(($data['status'] ?? '') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($data['status'] ?? '') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($data['keyword'] ?? null, fn ($query, string $keyword) => $query->where('name', 'like', '%'.trim($keyword).'%'))
            ->orderByDesc('is_active')
            ->orderBy('min_courts')
            ->get()
            ->map(fn (PlatformFeeTier $tier): array => $this->tierPayload($tier));

        return response()->json($tiers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->tierRules());

        $tier = DB::transaction(function () use ($data): PlatformFeeTier {
            $tier = PlatformFeeTier::query()->create($this->tierData($data));
            $this->rebalanceActiveRanges();

            return $tier->fresh();
        });

        return response()->json([
            'message' => 'Đã tạo bậc phí nền tảng.',
            'data' => $this->tierPayload($tier->loadCount(['ledgers as usage_count'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tier = PlatformFeeTier::query()->findOrFail($id);
        $data = $request->validate($this->tierRules($tier->id));

        $tier = DB::transaction(function () use ($tier, $data): PlatformFeeTier {
            $tier->forceFill($this->tierData($data))->save();
            $this->rebalanceActiveRanges();

            return $tier->fresh();
        });

        return response()->json([
            'message' => 'Đã cập nhật bậc phí nền tảng.',
            'data' => $this->tierPayload($tier->loadCount(['ledgers as usage_count'])),
        ]);
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $tier = DB::transaction(function () use ($id): PlatformFeeTier {
            $tier = PlatformFeeTier::query()->lockForUpdate()->findOrFail($id);
            $tier->forceFill(['is_active' => false])->save();
            $this->rebalanceActiveRanges();

            return $tier->fresh();
        });

        return response()->json([
            'message' => 'Đã ngừng dùng bậc phí.',
            'data' => $this->tierPayload($tier->loadCount(['ledgers as usage_count'])),
        ]);
    }

    public function reactivate(int $id): JsonResponse
    {
        $tier = DB::transaction(function () use ($id): PlatformFeeTier {
            $tier = PlatformFeeTier::query()->lockForUpdate()->findOrFail($id);
            $tier->forceFill(['is_active' => true])->save();
            $this->rebalanceActiveRanges();

            return $tier->fresh();
        });

        return response()->json([
            'message' => 'Đã bật lại bậc phí.',
            'data' => $this->tierPayload($tier->loadCount(['ledgers as usage_count'])),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tier = DB::transaction(function () use ($id): ?PlatformFeeTier {
            $tier = PlatformFeeTier::query()->lockForUpdate()->findOrFail($id);
            $usageCount = VenuePlatformFeeLedger::query()->where('tier_id', $tier->id)->count();

            if ($usageCount > 0) {
                $tier->forceFill(['is_active' => false])->save();
                $this->rebalanceActiveRanges();

                return $tier->fresh();
            }

            $tier->delete();
            $this->rebalanceActiveRanges();

            return null;
        });

        return response()->json([
            'message' => $tier ? 'Bậc phí đã có ledger sử dụng nên chỉ ngừng dùng.' : 'Đã xóa bậc phí.',
            'data' => $tier ? $this->tierPayload($tier->loadCount(['ledgers as usage_count'])) : null,
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json($this->settingsPayload());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'default_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'auto_mark_overdue' => ['required', 'boolean'],
            'lock_reason' => ['required', 'string', 'max:500'],
        ]);

        SystemPolicy::query()->updateOrCreate(
            ['key' => self::SETTINGS_KEY, 'version' => 1],
            [
                'title' => 'Cài đặt phí duy trì',
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'type' => 'general',
                'policy_type' => 'platform_fee',
                'policy_category' => 'numeric_threshold',
                'status' => 'published',
                'is_active' => true,
                'effective_from' => now(),
                'updated_by' => $request->user()?->id,
                'created_by' => $request->user()?->id,
            ],
        );

        return response()->json([
            'message' => 'Đã lưu cài đặt phí duy trì.',
            'data' => $this->settingsPayload(),
        ]);
    }

    private function tierRules(?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('platform_fee_tiers', 'name')->ignore($ignoreId),
            ],
            'min_courts' => ['required', 'integer', 'min:1'],
            'price_per_court_month' => ['required', 'numeric', 'min:1'],
            'annual_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function tierData(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'min_courts' => (int) $data['min_courts'],
            'max_courts' => null,
            'price_per_court_month' => round((float) $data['price_per_court_month'], 2),
            'annual_discount_percent' => round((float) ($data['annual_discount_percent'] ?? 0), 2),
            'is_active' => (bool) $data['is_active'],
            'effective_from' => now(),
        ];
    }

    private function rebalanceActiveRanges(): void
    {
        $tiers = PlatformFeeTier::query()
            ->where('is_active', true)
            ->orderBy('min_courts')
            ->lockForUpdate()
            ->get();

        foreach ($tiers as $index => $tier) {
            $next = $tiers[$index + 1] ?? null;
            $tier->forceFill([
                'max_courts' => $next ? max((int) $tier->min_courts, (int) $next->min_courts - 1) : null,
            ])->save();
        }
    }

    private function tierPayload(PlatformFeeTier $tier): array
    {
        $annualDiscount = (float) $tier->annual_discount_percent;

        return [
            'id' => $tier->id,
            'name' => $tier->name,
            'min_courts' => (int) $tier->min_courts,
            'max_courts' => $tier->max_courts !== null ? (int) $tier->max_courts : null,
            'price_per_court_month' => (float) $tier->price_per_court_month,
            'annual_discount_percent' => $annualDiscount,
            'discount_profile_id' => 'db-annual',
            'discount_1_month' => 0,
            'discount_3_months' => 0,
            'discount_6_months' => 0,
            'discount_9_months' => 0,
            'discount_12_months' => $annualDiscount,
            'is_active' => (bool) $tier->is_active,
            'note' => 'Dữ liệu từ DB. Hiện DB chỉ lưu giảm kỳ 12 tháng.',
            'usage_count' => (int) ($tier->usage_count ?? 0),
            'created_at' => $tier->created_at?->toISOString(),
            'updated_at' => $tier->updated_at?->toISOString(),
        ];
    }

    private function settingsPayload(): array
    {
        $defaults = [
            'default_due_days' => 7,
            'auto_mark_overdue' => true,
            'lock_reason' => 'Quá hạn phí duy trì hệ thống',
        ];

        $policy = SystemPolicy::query()
            ->where('key', self::SETTINGS_KEY)
            ->where('version', 1)
            ->first();

        if (! $policy) {
            return $defaults;
        }

        $data = json_decode($policy->content, true);

        return array_merge($defaults, is_array($data) ? $data : []);
    }
}
