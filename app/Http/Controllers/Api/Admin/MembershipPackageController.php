<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipPackage;
use App\Services\Memberships\SystemVipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipPackageController extends Controller
{
    public function __construct(private readonly SystemVipService $vip) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->vip->packagesPayload(),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $package = MembershipPackage::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'quarterly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'voucher_count_per_month' => ['required', 'integer', 'min:0', 'max:50'],
            'voucher_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'voucher_min_order_amount' => ['required', 'numeric', 'min:0'],
            'voucher_max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'cashback_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'match_post_limit_per_month' => ['required', 'integer', 'min:-1'],
            'priority_complaint' => ['required', 'boolean'],
            'badge_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:255'],
        ]);

        $data['voucher_count_per_month'] = 0;
        $data['voucher_discount_percent'] = 0;
        $data['voucher_min_order_amount'] = 0;
        $data['voucher_max_discount_amount'] = null;

        if ($package->type === 'free') {
            $data['monthly_price'] = 0;
            $data['quarterly_price'] = null;
            $data['yearly_price'] = null;
            $data['cashback_percent'] = 0;
            $data['priority_complaint'] = false;
            $data['badge_name'] = null;
        }

        $package->update($data);

        return response()->json([
            'message' => 'Đã cập nhật gói VIP hệ thống.',
            'data' => $this->vip->packagePayload($package->fresh()),
        ]);
    }
}
