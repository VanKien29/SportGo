<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemPolicy;
use App\Models\UserPolicyAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemPolicyController extends Controller
{
    /**
     * Kiểm tra xem người dùng có cần chấp nhận phiên bản chính sách mới nhất không.
     */
    public function checkAcceptance()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['needs_acceptance' => false]);
        }

        // Lấy chính sách 'general' mới nhất đang kích hoạt
        $latestPolicy = SystemPolicy::where('type', 'general')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();

        if (!$latestPolicy) {
            return response()->json(['needs_acceptance' => false]);
        }

        $accepted = UserPolicyAcceptance::where('user_id', $user->id)
            ->where('system_policy_id', $latestPolicy->id)
            ->where('policy_version', $latestPolicy->version)
            ->exists();

        if (!$accepted) {
            return response()->json([
                'needs_acceptance' => true,
                'policy' => $latestPolicy
            ]);
        }

        return response()->json(['needs_acceptance' => false]);
    }

    /**
     * Lưu xác nhận chấp nhận chính sách của người dùng.
     */
    public function accept(Request $request)
    {
        $request->validate([
            'policy_id' => 'required|string|exists:system_policies,id'
        ]);

        $user = Auth::user();
        $policy = SystemPolicy::findOrFail($request->policy_id);

        UserPolicyAcceptance::updateOrCreate(
            [
                'user_id' => $user->id,
                'system_policy_id' => $policy->id,
                'policy_version' => $policy->version,
            ],
            [
                'accepted_at' => now(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Bạn đã chấp nhận chính sách hệ thống.'
        ]);
    }
}
