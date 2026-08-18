<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\SystemPolicy;
use App\Services\Partner\PartnerOnboardingTermsService;
use Illuminate\Http\JsonResponse;

class PolicyController extends Controller
{
    public function index(PartnerOnboardingTermsService $onboardingTerms): JsonResponse
    {
        $policies = SystemPolicy::query()
            ->whereIn('key', [
                'terms',
                'booking_cancellation',
                'platform_fee',
                'venue_policy',
                'moderation',
                'partner_contract',
            ])
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            })
            ->orderByDesc('priority')
            ->orderByDesc('version')
            ->get()
            ->unique('key')
            ->values();

        return response()->json([
            'data' => [
                'policies' => $policies->map(fn (SystemPolicy $policy): array => [
                    'id' => $policy->id,
                    'key' => $policy->key,
                    'title' => $policy->title,
                    'content' => $policy->content,
                    'type' => $policy->type,
                    'policy_type' => $policy->policy_type ?: $policy->type,
                    'version' => (int) $policy->version,
                    'effective_from' => $policy->effective_from?->toDateString(),
                    'effective_to' => $policy->effective_to?->toDateString(),
                    'change_summary' => $policy->change_summary,
                ])->all(),
                'partner_onboarding' => $onboardingTerms->payload(),
            ],
        ]);
    }
}
