<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeePlanVersion;
use App\Services\Payments\PlatformFeePlanVersionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformFeePlanVersionController extends Controller
{
    public function __construct(private readonly PlatformFeePlanVersionService $plans) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'active', 'retired'])],
        ]);

        $plans = PlatformFeePlanVersion::query()
            ->with(['tiers', 'prepayDiscountRules', 'createdBy:id,full_name', 'publishedBy:id,full_name'])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PlatformFeePlanVersion $plan): array => $this->payload($plan));

        return response()->json(['data' => $plans]);
    }

    public function show(int $id): JsonResponse
    {
        $plan = PlatformFeePlanVersion::query()
            ->with(['tiers', 'prepayDiscountRules', 'createdBy:id,full_name', 'publishedBy:id,full_name'])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->payload($plan),
            'impact' => $this->plans->impactPreview($plan),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $source = isset($data['source_plan_version_id'])
            ? PlatformFeePlanVersion::query()->findOrFail($data['source_plan_version_id'])
            : null;
        $data['actor_id'] = $request->user()?->id;
        $plan = $this->plans->createDraft($data, $source);

        return response()->json([
            'message' => $source ? 'Đã nhân bản thành phiên bản nháp mới.' : 'Đã tạo phiên bản bảng giá nháp.',
            'data' => $this->payload($plan),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = PlatformFeePlanVersion::query()->findOrFail($id);
        $data = $request->validate($this->rules(false));
        $plan = $this->plans->updateDraft($plan, $data);

        return response()->json([
            'message' => 'Đã lưu cấu hình phiên bản nháp.',
            'data' => $this->payload($plan),
        ]);
    }

    public function schedule(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['effective_from' => ['required', 'date_format:Y-m-d']]);
        $plan = $this->plans->schedule(
            PlatformFeePlanVersion::query()->findOrFail($id),
            CarbonImmutable::parse($data['effective_from'], config('platform_fee.timezone')),
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Đã lên lịch áp dụng phiên bản bảng giá.',
            'data' => $this->payload($plan),
            'impact' => $this->plans->impactPreview($plan),
        ]);
    }

    public function cancelSchedule(int $id): JsonResponse
    {
        $plan = $this->plans->cancelSchedule(PlatformFeePlanVersion::query()->findOrFail($id));

        return response()->json([
            'message' => 'Đã hủy lịch áp dụng; phiên bản trở về trạng thái nháp.',
            'data' => $this->payload($plan),
        ]);
    }

    private function rules(bool $creating): array
    {
        return [
            'source_plan_version_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'exists:platform_fee_plan_versions,id'],
            'code' => [$creating ? 'required' : 'prohibited', 'string', 'max:50', Rule::unique('platform_fee_plan_versions', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_lead_days' => ['required', 'integer', 'min:0', 'max:28'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'notice_days' => ['required', 'integer', 'min:1', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'prepay_discounts' => [$creating ? 'prohibited' : 'sometimes', 'array'],
            'prepay_discounts.*.months' => ['required', 'integer', Rule::in(config('platform_fee.allowed_prepay_months'))],
            'prepay_discounts.*.discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'prepay_discounts.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function payload(PlatformFeePlanVersion $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'status' => $plan->status,
            'effective_from' => $plan->effective_from?->toDateString(),
            'effective_to' => $plan->effective_to?->toDateString(),
            'trial_days' => (int) $plan->trial_days,
            'invoice_lead_days' => (int) $plan->invoice_lead_days,
            'due_day' => (int) $plan->due_day,
            'notice_days' => (int) $plan->notice_days,
            'notes' => $plan->notes,
            'tiers' => $plan->tiers->map(fn ($tier): array => [
                'id' => $tier->id,
                'name' => $tier->name,
                'min_courts' => (int) $tier->min_courts,
                'max_courts' => $tier->max_courts !== null ? (int) $tier->max_courts : null,
                'price_per_court_month' => (float) $tier->price_per_court_month,
                'is_active' => (bool) $tier->is_active,
            ])->values(),
            'prepay_discounts' => $plan->prepayDiscountRules->map(fn ($rule): array => [
                'months' => (int) $rule->months,
                'discount_percent' => (float) $rule->discount_percent,
                'is_active' => (bool) $rule->is_active,
            ])->values(),
            'created_by_name' => $plan->createdBy?->full_name,
            'published_by_name' => $plan->publishedBy?->full_name,
            'scheduled_at' => $plan->scheduled_at?->toIso8601String(),
            'activated_at' => $plan->activated_at?->toIso8601String(),
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
