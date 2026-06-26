<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\MembershipPackage;
use App\Services\Memberships\SystemVipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

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

        try {
            $result = $this->vip->subscribe($request->user(), $package, $data['billing_cycle']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Đã tạo thông tin thanh toán gói VIP. Vui lòng chuyển khoản để kích hoạt gói.',
            'subscription' => $this->vip->subscriptionPayload($result['subscription']),
            'payment' => $result['payment'],
            'payment_account' => $result['payment_account'],
            'system_bank_account' => $result['system_bank_account'],
            'transfer_content' => $result['transfer_content'],
            'qr_url' => $result['qr_url'],
        ], 201);
    }
}
