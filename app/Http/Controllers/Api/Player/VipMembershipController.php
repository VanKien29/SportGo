<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\MembershipPackage;
use App\Services\Memberships\SystemVipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VipMembershipController extends Controller
{
    public function __construct(private readonly SystemVipService $vip)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'packages' => $this->vip->packagesPayload(),
            'subscription' => $this->vip->currentSubscriptionPayload($request->user()),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => ['required', 'uuid', 'exists:membership_packages,id'],
            'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
        ]);

        $package = MembershipPackage::query()->findOrFail($data['package_id']);
        $subscription = $this->vip->subscribe($request->user(), $package, $data['billing_cycle']);

        return response()->json([
            'message' => 'Đã kích hoạt gói VIP hệ thống.',
            'subscription' => $this->vip->subscriptionPayload($subscription),
        ], 201);
    }
}
