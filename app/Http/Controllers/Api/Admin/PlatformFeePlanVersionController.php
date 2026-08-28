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
            'q' => ['nullable', 'string', 'max:100'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $plans = PlatformFeePlanVersion::query()
            ->with(['tiers', 'prepayDiscountRules', 'createdBy:id,full_name', 'publishedBy:id,full_name'])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(trim((string) ($data['q'] ?? '')), function ($query, string $keyword): void {
                $query->where(function ($search) use ($keyword): void {
                    $search->where('code', 'like', '%'.$keyword.'%')
                        ->orWhere('name', 'like', '%'.$keyword.'%');
                });
            })
            ->when($data['effective_from'] ?? null, fn ($query, string $date) => $query->whereDate('effective_from', '>=', $date))
            ->when($data['effective_to'] ?? null, fn ($query, string $date) => $query->where(function ($range) use ($date): void {
                $range->whereNull('effective_to')->orWhereDate('effective_to', '<=', $date);
            }))
            ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 15));

        $plans->getCollection()->transform(fn (PlatformFeePlanVersion $plan): array => $this->payload($plan));

        $statusSummary = PlatformFeePlanVersion::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        return response()->json(array_merge($plans->toArray(), [
            'status_summary' => [
                'total' => (int) $statusSummary->sum(),
                'active' => (int) ($statusSummary['active'] ?? 0),
                'scheduled' => (int) ($statusSummary['scheduled'] ?? 0),
                'draft' => (int) ($statusSummary['draft'] ?? 0),
                'retired' => (int) ($statusSummary['retired'] ?? 0),
            ],
        ]));
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
        $planModel = PlatformFeePlanVersion::query()->findOrFail($id);
        $hasDraftData = $request->has('name');
        $data = $request->validate(array_merge(
            ['effective_from' => ['required', 'date_format:Y-m-d']],
            $hasDraftData ? $this->rules(false) : ['expected_revision' => ['nullable', 'integer', 'min:1']],
        ));
        $effectiveFrom = CarbonImmutable::parse($data['effective_from'], config('platform_fee.timezone'));
        $plan = $hasDraftData
            ? $this->plans->updateAndSchedule($planModel, $data, $effectiveFrom, $request->user()?->id)
            : $this->plans->schedule(
                $planModel,
                $effectiveFrom,
                $request->user()?->id,
                $data['expected_revision'] ?? null,
            );

        return response()->json([
            'message' => 'Đã lên lịch áp dụng phiên bản bảng giá.',
            'data' => $this->payload($plan),
            'impact' => $this->plans->impactPreview($plan),
        ]);
    }

    public function cancelSchedule(Request $request, int $id): JsonResponse
    {
        $plan = $this->plans->cancelSchedule(
            PlatformFeePlanVersion::query()->findOrFail($id),
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Đã hủy lịch áp dụng; phiên bản trở về trạng thái nháp.',
            'data' => $this->payload($plan),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = PlatformFeePlanVersion::query()->findOrFail($id);
        $this->plans->deleteDraft($plan);

        return response()->json(['message' => 'Đã xóa phiên bản bảng giá nháp.']);
    }

    private function rules(bool $creating): array
    {
        return [
            'source_plan_version_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'exists:platform_fee_plan_versions,id'],
            'code' => [$creating ? 'required' : 'prohibited', 'string', 'min:3', 'max:50', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', Rule::unique('platform_fee_plan_versions', 'code')],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'billing_anchor_day' => ['required', 'integer', 'min:1', 'max:28'],
            'invoice_lead_days' => ['required', 'integer', 'min:0', 'max:28'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'notice_days' => ['required', 'integer', 'min:1', 'max:180'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expected_revision' => [$creating ? 'prohibited' : 'nullable', 'integer', 'min:1'],
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
            'revision' => (int) $plan->revision,
            'effective_from' => $plan->effective_from?->toDateString(),
            'effective_to' => $plan->effective_to?->toDateString(),
            'trial_days' => (int) $plan->trial_days,
            'billing_anchor_day' => (int) $plan->billing_anchor_day,
            'invoice_lead_days' => (int) $plan->invoice_lead_days,
            'due_day' => (int) $plan->due_day,
            'notice_days' => (int) $plan->notice_days,
            'notification_mode' => $plan->notification_mode,
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
            'cancelled_at' => $plan->cancelled_at?->toIso8601String(),
            'activated_at' => $plan->activated_at?->toIso8601String(),
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
