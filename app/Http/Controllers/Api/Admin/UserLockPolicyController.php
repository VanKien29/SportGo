<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserLockPolicyResource;
use App\Models\UserLockPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLockPolicyController extends Controller
{
    /**
     * GET /admin/user-lock-policy
     * Trả về policy đang active, nếu không có trả default.
     */
    public function show(): JsonResponse
    {
        $policy = UserLockPolicy::query()
            ->where('is_active', true)
            ->with(['creator:id,username,full_name', 'updater:id,username,full_name'])
            ->latest()
            ->first();

        if (! $policy) {
            return response()->json([
                'data' => [
                    'id' => null,
                    'auto_lock_enabled' => false,
                    'report_threshold' => 5,
                    'lock_duration_hours' => null,
                    'is_active' => false,
                    'created_by' => null,
                    'created_by_name' => null,
                    'updated_by' => null,
                    'updated_by_name' => null,
                    'created_at' => null,
                    'updated_at' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => new UserLockPolicyResource($policy),
        ]);
    }

    /**
     * POST /admin/user-lock-policy
     * Tạo/cập nhật policy (deactivate cũ, tạo mới active).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'auto_lock_enabled' => ['required', 'boolean'],
            'report_threshold' => ['required', 'integer', 'min:1'],
            'lock_duration_hours' => ['nullable', 'integer', 'min:1'],
        ], [
            'auto_lock_enabled.required' => 'Vui lòng chọn bật/tắt khóa tự động.',
            'report_threshold.required' => 'Vui lòng nhập số lượt báo cáo.',
            'report_threshold.min' => 'Số lượt báo cáo tối thiểu là 1.',
            'lock_duration_hours.min' => 'Thời hạn khóa tối thiểu là 1 giờ.',
        ]);

        // Deactivate all old policies
        UserLockPolicy::query()->where('is_active', true)->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        $policy = UserLockPolicy::query()->create([
            'auto_lock_enabled' => $data['auto_lock_enabled'],
            'report_threshold' => $data['report_threshold'],
            'lock_duration_hours' => $data['lock_duration_hours'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $policy->load(['creator:id,username,full_name', 'updater:id,username,full_name']);

        return response()->json([
            'message' => 'Lưu cấu hình chính sách khóa thành công.',
            'data' => new UserLockPolicyResource($policy),
        ]);
    }
}
