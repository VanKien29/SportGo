<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemPolicy;
use App\Models\UserPolicyAcceptance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyAcceptanceController extends Controller
{
    public function required(Request $request): JsonResponse
    {
        $user = $request->user();

        $policies = SystemPolicy::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->orderBy('type')
            ->orderBy('key')
            ->get()
            ->filter(function (SystemPolicy $policy) use ($user) {
                return ! UserPolicyAcceptance::query()
                    ->where('user_id', $user->id)
                    ->where('system_policy_id', $policy->id)
                    ->where('policy_version', (string) $policy->version)
                    ->exists();
            })
            ->values();

        return response()->json([
            'required' => $policies->isNotEmpty(),
            'policies' => $policies,
        ]);
    }

    public function accept(Request $request, SystemPolicy $policy): JsonResponse
    {
        if (! $policy->is_active) {
            return response()->json(['message' => 'Chính sách này hiện không còn hiệu lực.'], 422);
        }

        UserPolicyAcceptance::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'system_policy_id' => $policy->id,
                'policy_version' => (string) $policy->version,
            ],
            [
                'accepted_at' => now(),
            ],
        );

        return response()->json(['message' => 'Đã ghi nhận chấp nhận chính sách.']);
    }
}
