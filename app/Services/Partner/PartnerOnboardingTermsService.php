<?php

namespace App\Services\Partner;

use App\Models\PlatformFeeTier;
use App\Models\SystemPolicy;
use App\Services\Policies\PolicyConfigurationService;

class PartnerOnboardingTermsService
{
    public function payload(): array
    {
        $tiers = PlatformFeeTier::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->orderBy('min_courts')
            ->get();

        $policies = SystemPolicy::query()
            ->whereIn('key', ['terms', 'platform_fee', 'partner_contract'])
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->orderByDesc('version')
            ->get()
            ->unique('key')
            ->keyBy('key');

        $feeSettings = $this->feeSettings($policies->get('platform_fee'));

        return [
            'platform_fee' => [
                'title' => 'Phí nền tảng',
                'billing_cycle' => 'monthly',
                'billing_cycle_label' => 'Theo tháng',
                'price_basis' => 'per_court_month',
                'price_basis_label' => 'Tính theo số sân con mỗi tháng',
                'tiers' => $tiers->map(fn (PlatformFeeTier $tier): array => [
                    'id' => $tier->id,
                    'name' => $tier->name,
                    'min_courts' => (int) $tier->min_courts,
                    'max_courts' => $tier->max_courts === null ? null : (int) $tier->max_courts,
                    'price_per_court_month' => (float) $tier->price_per_court_month,
                    'annual_discount_percent' => (float) $tier->annual_discount_percent,
                ])->values(),
                'settings' => $feeSettings,
                'summary' => 'Phí được tính theo số sân con và kỳ thanh toán đã chọn. Mức phí áp dụng được chốt lại tại thời điểm tạo kỳ phí.',
            ],
            'policies' => collect(['terms', 'platform_fee', 'partner_contract'])
                ->map(fn (string $key) => $policies->get($key))
                ->filter()
                ->map(fn (SystemPolicy $policy): array => [
                    'key' => $policy->key,
                    'title' => $policy->title,
                    'version' => (int) $policy->version,
                    'content' => $policy->content,
                    'effective_from' => $policy->effective_from?->toDateString(),
                    'require_reaccept' => (bool) $policy->require_reaccept,
                ])->values(),
            'notice' => 'Vui lòng đọc phí nền tảng và chính sách trước khi gửi hồ sơ. Nội dung áp dụng là phiên bản đang có hiệu lực tại thời điểm bạn xác nhận.',
        ];
    }

    private function feeSettings(?SystemPolicy $policy): array
    {
        $defaults = [
            'default_due_days' => 3,
            'lock_reason' => 'Cụm sân của bạn đã đến hoặc quá hạn phí nền tảng.',
        ];

        if (! $policy) {
            return $defaults;
        }

        $settings = app(PolicyConfigurationService::class)->extractConfigurationData(
            $policy->loadMissing('rules'),
        );

        return [
            'default_due_days' => (int) ($settings['remind_before_days'] ?? $defaults['default_due_days']),
            'lock_reason' => (string) ($settings['message_template'] ?? $defaults['lock_reason']),
        ];
    }
}
