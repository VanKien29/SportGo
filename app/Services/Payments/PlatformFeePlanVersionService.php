<?php

namespace App\Services\Payments;

use App\Models\PlatformFeePlanVersion;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformFeePlanVersionService
{
    public function createDraft(array $data, ?PlatformFeePlanVersion $source = null): PlatformFeePlanVersion
    {
        return DB::transaction(function () use ($data, $source): PlatformFeePlanVersion {
            $plan = PlatformFeePlanVersion::query()->create([
                'code' => trim($data['code']),
                'name' => trim($data['name']),
                'status' => 'draft',
                'trial_days' => (int) ($data['trial_days'] ?? $source?->trial_days ?? 30),
                'invoice_lead_days' => (int) ($data['invoice_lead_days'] ?? $source?->invoice_lead_days ?? 7),
                'due_day' => (int) ($data['due_day'] ?? $source?->due_day ?? 5),
                'notice_days' => (int) ($data['notice_days'] ?? $source?->notice_days ?? 30),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['actor_id'] ?? null,
            ]);

            if ($source) {
                $source->loadMissing(['tiers', 'prepayDiscountRules']);
                foreach ($source->tiers as $tier) {
                    $plan->tiers()->create([
                        'name' => $tier->name,
                        'min_courts' => $tier->min_courts,
                        'max_courts' => $tier->max_courts,
                        'price_per_court_month' => $tier->price_per_court_month,
                        'annual_discount_percent' => $tier->annual_discount_percent,
                        'is_active' => $tier->is_active,
                        'effective_from' => now(),
                    ]);
                }
                foreach ($source->prepayDiscountRules as $rule) {
                    $plan->prepayDiscountRules()->create([
                        'months' => $rule->months,
                        'discount_percent' => $rule->discount_percent,
                        'is_active' => $rule->is_active,
                    ]);
                }
            }

            return $this->load($plan);
        });
    }

    public function updateDraft(PlatformFeePlanVersion $plan, array $data): PlatformFeePlanVersion
    {
        $this->assertDraft($plan);
        if (array_key_exists('prepay_discounts', $data)) {
            $this->validateDiscountRules($data['prepay_discounts']);
        }

        return DB::transaction(function () use ($plan, $data): PlatformFeePlanVersion {
            $plan->forceFill([
                'name' => trim($data['name']),
                'trial_days' => (int) $data['trial_days'],
                'invoice_lead_days' => (int) $data['invoice_lead_days'],
                'due_day' => (int) $data['due_day'],
                'notice_days' => (int) $data['notice_days'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            foreach ($data['prepay_discounts'] ?? [] as $discount) {
                $plan->prepayDiscountRules()->updateOrCreate(
                    ['months' => (int) $discount['months']],
                    [
                        'discount_percent' => round((float) $discount['discount_percent'], 2),
                        'is_active' => (bool) ($discount['is_active'] ?? true),
                    ],
                );
            }

            return $this->load($plan);
        });
    }

    public function schedule(PlatformFeePlanVersion $plan, CarbonImmutable $effectiveFrom, ?int $actorId): PlatformFeePlanVersion
    {
        $this->assertDraft($plan);
        $plan->loadMissing(['tiers', 'prepayDiscountRules']);
        $this->validateCoverage($plan);
        $this->validateDiscountRules($plan->prepayDiscountRules->toArray(), true);

        $earliest = CarbonImmutable::today(config('platform_fee.timezone'))->addDays((int) $plan->notice_days);
        if ($effectiveFrom->startOfDay()->lt($earliest)) {
            throw ValidationException::withMessages([
                'effective_from' => [sprintf(
                    'Ngày áp dụng phải từ %s để bảo đảm thời gian thông báo %d ngày.',
                    $earliest->format('d/m/Y'),
                    (int) $plan->notice_days,
                )],
            ]);
        }

        $hasScheduled = PlatformFeePlanVersion::query()
            ->where('status', 'scheduled')
            ->whereKeyNot($plan->id)
            ->exists();
        if ($hasScheduled) {
            throw ValidationException::withMessages([
                'effective_from' => ['Đang có một phiên bản chờ áp dụng. Hãy hủy lịch hoặc chờ phiên bản đó có hiệu lực.'],
            ]);
        }

        $plan->forceFill([
            'status' => 'scheduled',
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => null,
            'published_by' => $actorId,
            'scheduled_at' => now(),
        ])->save();

        return $this->load($plan);
    }

    public function cancelSchedule(PlatformFeePlanVersion $plan): PlatformFeePlanVersion
    {
        if ($plan->status !== 'scheduled') {
            abort(409, 'Chỉ phiên bản đang chờ áp dụng mới được hủy lịch.');
        }
        if (VenuePlatformFeeLedger::query()
            ->where('plan_version_id', $plan->id)
            ->whereNotIn('status', ['cancelled', 'voided'])
            ->exists()) {
            abort(409, 'Phiên bản đã được chốt vào kỳ phí tương lai; phải hủy và phát hành lại các kỳ liên quan trước.');
        }

        $plan->forceFill([
            'status' => 'draft',
            'effective_from' => null,
            'published_by' => null,
            'scheduled_at' => null,
        ])->save();

        return $this->load($plan);
    }

    /** @return array{activated:int,plans:array<int,PlatformFeePlanVersion>} */
    public function activateDueVersions(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today(config('platform_fee.timezone'));
        $activated = [];

        DB::transaction(function () use ($today, &$activated): void {
            $duePlans = PlatformFeePlanVersion::query()
                ->where('status', 'scheduled')
                ->whereDate('effective_from', '<=', $today->toDateString())
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            foreach ($duePlans as $plan) {
                PlatformFeePlanVersion::query()
                    ->where('status', 'active')
                    ->whereKeyNot($plan->id)
                    ->update([
                        'status' => 'retired',
                        'effective_to' => CarbonImmutable::instance($plan->effective_from)->subDay()->toDateString(),
                        'retired_at' => now(),
                    ]);

                $plan->forceFill([
                    'status' => 'active',
                    'activated_at' => now(),
                    'effective_to' => null,
                ])->save();
                $activated[] = $this->load($plan);
            }
        }, 3);

        return ['activated' => count($activated), 'plans' => $activated];
    }

    /** @return array<string,int|float> */
    public function impactPreview(PlatformFeePlanVersion $plan): array
    {
        $plan->loadMissing('tiers');
        $activeClusters = VenueCluster::query()->where('status', 'active')->withCount('venueCourts')->get();
        $covered = 0;
        $monthlyAmount = 0.0;

        foreach ($activeClusters as $cluster) {
            $tier = $plan->tiers
                ->where('is_active', true)
                ->filter(fn ($item): bool => $item->min_courts <= $cluster->venue_courts_count
                    && ($item->max_courts === null || $item->max_courts >= $cluster->venue_courts_count))
                ->sortByDesc('min_courts')
                ->first();
            if ($tier) {
                $covered++;
                $monthlyAmount += $cluster->venue_courts_count * (float) $tier->price_per_court_month;
            }
        }

        return [
            'active_clusters' => $activeClusters->count(),
            'covered_clusters' => $covered,
            'uncovered_clusters' => $activeClusters->count() - $covered,
            'estimated_monthly_amount' => round($monthlyAmount, 2),
        ];
    }

    private function validateCoverage(PlatformFeePlanVersion $plan): void
    {
        $tiers = $plan->tiers->where('is_active', true)->sortBy('min_courts')->values();
        if ($tiers->isEmpty() || (int) $tiers->first()->min_courts !== 1) {
            throw ValidationException::withMessages([
                'tiers' => ['Phiên bản phải có ít nhất một bậc đang dùng và bắt đầu từ 1 sân.'],
            ]);
        }
        foreach ($tiers as $index => $tier) {
            $next = $tiers[$index + 1] ?? null;
            if ($next && (int) $tier->max_courts + 1 !== (int) $next->min_courts) {
                throw ValidationException::withMessages([
                    'tiers' => ['Các bậc phí phải phủ liên tục, không được để hở hoặc chồng số lượng sân.'],
                ]);
            }
        }
    }

    private function validateDiscountRules(array $rules, bool $requireAllPeriods = false): void
    {
        $active = collect($rules)
            ->filter(fn (array $rule): bool => (bool) ($rule['is_active'] ?? true))
            ->sortBy(fn (array $rule): int => (int) $rule['months'])
            ->values();
        $months = $active->pluck('months')->map(fn ($month): int => (int) $month);

        if ($months->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prepay_discounts' => ['Mỗi kỳ hạn trả trước chỉ được cấu hình một mức giảm.'],
            ]);
        }

        $allowedPeriods = collect(config('platform_fee.allowed_prepay_months'))->map(fn ($month): int => (int) $month)->sort()->values();
        if ($requireAllPeriods && $months->sort()->values()->all() !== $allowedPeriods->all()) {
            throw ValidationException::withMessages([
                'prepay_discounts' => ['Phải cấu hình đủ mức giảm cho các kỳ 1, 3, 6, 9 và 12 tháng trước khi công bố.'],
            ]);
        }

        $previous = 0.0;
        foreach ($active as $rule) {
            $discount = (float) $rule['discount_percent'];
            if ($discount + 0.001 < $previous) {
                throw ValidationException::withMessages([
                    'prepay_discounts' => ['Mức giảm không được thấp hơn khi kỳ hạn trả trước dài hơn.'],
                ]);
            }
            $previous = $discount;
        }
    }

    private function assertDraft(PlatformFeePlanVersion $plan): void
    {
        if ($plan->status !== 'draft') {
            abort(409, 'Phiên bản đã công bố không được sửa trực tiếp. Hãy tạo phiên bản nháp mới.');
        }
    }

    private function load(PlatformFeePlanVersion $plan): PlatformFeePlanVersion
    {
        return $plan->fresh(['tiers', 'prepayDiscountRules', 'createdBy:id,full_name', 'publishedBy:id,full_name']);
    }
}
