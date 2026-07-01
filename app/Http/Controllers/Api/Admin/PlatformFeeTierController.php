<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeTier;
use App\Models\SystemPolicy;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $data = $request->validate($this->tierRules(), $this->tierValidationMessages());

        $tier = DB::transaction(function () use ($data): PlatformFeeTier {
            $this->validateUniqueName(null, (string) $data['name']);
            $this->validateUniqueMinimum(null, (int) $data['min_courts']);
            $this->validateProposedActiveCoverage(null, (bool) $data['is_active'], (int) $data['min_courts']);
            $this->validateProposedActivePrice(
                null,
                (bool) $data['is_active'],
                (int) $data['min_courts'],
                (float) $data['price_per_court_month'],
            );
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
        $data = $request->validate($this->tierRules($tier->id), $this->tierValidationMessages());

        $tier = DB::transaction(function () use ($tier, $data): PlatformFeeTier {
            $this->validateUniqueName($tier->id, (string) $data['name']);
            $this->validateUniqueMinimum($tier->id, (int) $data['min_courts']);
            $this->validateProposedActiveCoverage($tier->id, (bool) $data['is_active'], (int) $data['min_courts']);
            $this->validateProposedActivePrice(
                $tier->id,
                (bool) $data['is_active'],
                (int) $data['min_courts'],
                (float) $data['price_per_court_month'],
            );
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
            $this->validateProposedActiveCoverage($tier->id, false, (int) $tier->min_courts);
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
            $this->validateUniqueMinimum($tier->id, (int) $tier->min_courts);
            $this->validateProposedActiveCoverage($tier->id, true, (int) $tier->min_courts);
            $this->validateProposedActivePrice(
                $tier->id,
                true,
                (int) $tier->min_courts,
                (float) $tier->price_per_court_month,
            );
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

            if ($tier->is_active) {
                $this->validateProposedActiveCoverage($tier->id, false, (int) $tier->min_courts);
            }

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
            'default_due_days' => ['required', 'integer', 'min:1', 'max:30'],
            'lock_reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'default_due_days.required' => 'Vui lòng nhập số ngày nhắc trước hạn.',
            'default_due_days.integer' => 'Số ngày nhắc trước hạn phải là số nguyên.',
            'default_due_days.min' => 'Số ngày nhắc trước hạn phải từ 1 ngày.',
            'default_due_days.max' => 'Số ngày nhắc trước hạn không được vượt quá 30 ngày.',
            'lock_reason.required' => 'Vui lòng nhập lý do khóa cụm sân mặc định.',
            'lock_reason.min' => 'Lý do khóa cụm sân phải có ít nhất 3 ký tự.',
            'lock_reason.max' => 'Lý do khóa cụm sân không được vượt quá 500 ký tự.',
        ]);

        $data['lock_reason'] = trim($data['lock_reason']);

        SystemPolicy::query()->updateOrCreate(
            ['key' => self::SETTINGS_KEY, 'version' => 1],
            [
                'title' => 'Cài đặt phí duy trì',
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'type' => 'general',
                'policy_type' => 'platform_fee',
                'policy_category' => 'numeric_threshold',
                'status' => 'active',
                'is_active' => true,
                'effective_from' => now(),
                'published_at' => now(),
                'published_by' => $request->user()?->id,
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
            ],
            'min_courts' => [
                'required',
                'integer',
                'min:1',
            ],
            'price_per_court_month' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'annual_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function tierValidationMessages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên bậc phí.',
            'name.unique' => 'Tên bậc phí đang trùng với một bậc khác.',
            'name.max' => 'Tên bậc phí không được vượt quá 50 ký tự.',
            'min_courts.required' => 'Vui lòng nhập số sân tối thiểu.',
            'min_courts.integer' => 'Số sân tối thiểu phải là số nguyên.',
            'min_courts.min' => 'Số sân tối thiểu phải lớn hơn hoặc bằng 1.',
            'min_courts.unique' => 'Số sân tối thiểu đang trùng với một bậc phí khác.',
            'price_per_court_month.required' => 'Vui lòng nhập giá theo sân mỗi tháng.',
            'price_per_court_month.integer' => 'Giá theo sân mỗi tháng phải là số nguyên VND.',
            'price_per_court_month.min' => 'Giá theo sân mỗi tháng phải lớn hơn 0.',
            'price_per_court_month.max' => 'Giá theo sân mỗi tháng vượt quá giới hạn cho phép.',
            'annual_discount_percent.numeric' => 'Mức giảm kỳ 12 tháng phải là số.',
            'annual_discount_percent.min' => 'Mức giảm kỳ 12 tháng không được nhỏ hơn 0%.',
            'annual_discount_percent.max' => 'Mức giảm kỳ 12 tháng không được lớn hơn 100%.',
        ];
    }

    private function validateUniqueName(?int $ignoreId, string $name): void
    {
        $hasDuplicate = PlatformFeeTier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($hasDuplicate) {
            throw ValidationException::withMessages([
                'name' => ['Tên bậc phí đang trùng với một bậc phí khác.'],
            ]);
        }
    }

    private function validateUniqueMinimum(?int $ignoreId, int $minCourts): void
    {
        $hasDuplicate = PlatformFeeTier::query()
            ->where('min_courts', $minCourts)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->exists();

        if ($hasDuplicate) {
            throw ValidationException::withMessages([
                'min_courts' => ['Số sân tối thiểu đang trùng với một bậc phí khác.'],
            ]);
        }
    }

    private function validateProposedActiveCoverage(?int $changingId, bool $isActive, int $minCourts): void
    {
        $activeMinimums = PlatformFeeTier::query()
            ->where('is_active', true)
            ->when($changingId !== null, fn ($query) => $query->whereKeyNot($changingId))
            ->lockForUpdate()
            ->pluck('min_courts')
            ->map(fn ($value): int => (int) $value)
            ->all();

        if ($isActive) {
            $activeMinimums[] = $minCourts;
        }

        if ($activeMinimums !== [] && min($activeMinimums) !== 1) {
            throw ValidationException::withMessages([
                'min_courts' => ['Bậc phí đang dùng đầu tiên phải bắt đầu từ 1 sân.'],
            ]);
        }
    }

    private function validateProposedActivePrice(
        ?int $changingId,
        bool $isActive,
        int $minCourts,
        float $price,
    ): void {
        if (! $isActive) {
            return;
        }

        $activeTiers = PlatformFeeTier::query()
            ->where('is_active', true)
            ->when($changingId !== null, fn ($query) => $query->whereKeyNot($changingId))
            ->lockForUpdate()
            ->get(['min_courts', 'price_per_court_month']);

        $previousTier = $activeTiers
            ->where('min_courts', '<', $minCourts)
            ->sortByDesc('min_courts')
            ->first();
        $nextTier = $activeTiers
            ->where('min_courts', '>', $minCourts)
            ->sortBy('min_courts')
            ->first();

        if ($previousTier && $price >= (float) $previousTier->price_per_court_month) {
            throw ValidationException::withMessages([
                'price_per_court_month' => [
                    'Giá bậc này phải thấp hơn giá của bậc ít sân hơn ('.number_format((float) $previousTier->price_per_court_month, 0, ',', '.').' đ).',
                ],
            ]);
        }

        if ($nextTier && $price <= (float) $nextTier->price_per_court_month) {
            throw ValidationException::withMessages([
                'price_per_court_month' => [
                    'Giá bậc này phải cao hơn giá của bậc nhiều sân hơn ('.number_format((float) $nextTier->price_per_court_month, 0, ',', '.').' đ).',
                ],
            ]);
        }
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
            $maxCourts = $next ? max((int) $tier->min_courts, (int) $next->min_courts - 1) : null;
            $attributes = ['max_courts' => $maxCourts];

            if ($this->usesRangeAsName($tier->name)) {
                $attributes['name'] = $this->rangeName((int) $tier->min_courts, $maxCourts);
            }

            $tier->forceFill($attributes)->save();
        }
    }

    private function usesRangeAsName(string $name): bool
    {
        return preg_match('/^(?:\d+\s*[-–]\s*\d+\s*sân|từ\s+\d+\s+sân\s+trở\s+lên)$/iu', trim($name)) === 1;
    }

    private function rangeName(int $minCourts, ?int $maxCourts): string
    {
        return $maxCourts === null
            ? "Từ {$minCourts} sân trở lên"
            : "{$minCourts}-{$maxCourts} sân";
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
            'note' => null,
            'usage_count' => (int) ($tier->usage_count ?? 0),
            'created_at' => $tier->created_at?->toISOString(),
            'updated_at' => $tier->updated_at?->toISOString(),
        ];
    }

    private function settingsPayload(): array
    {
        $defaults = [
            'default_due_days' => 7,
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

        if (! is_array($data)) {
            return $defaults;
        }

        return [
            'default_due_days' => (int) ($data['default_due_days'] ?? $defaults['default_due_days']),
            'lock_reason' => (string) ($data['lock_reason'] ?? $defaults['lock_reason']),
        ];
    }
}
