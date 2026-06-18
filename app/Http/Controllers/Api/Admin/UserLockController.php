<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserLockLogResource;
use App\Models\User;
use App\Services\Admin\UserLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserLockController extends Controller
{
    public function __construct(
        private readonly UserLockService $lockService,
    ) {}

    /**
     * POST /admin/users/{user}/lock
     * Khóa tài khoản thủ công.
     */
    public function lock(Request $request, string $user): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'duration_hours' => ['nullable', 'integer', 'min:1'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do khóa.',
        ]);

        $target = User::query()->findOrFail($user);

        /** @var User $admin */
        $admin = $request->user();

        $this->lockService->lockManual(
            $target,
            $admin,
            $data['reason'],
            $data['duration_hours'] ?? null,
        );

        return response()->json([
            'message' => 'Khóa tài khoản thành công.',
        ]);
    }

    /**
     * POST /admin/users/{user}/unlock
     * Mở khóa tài khoản.
     */
    public function unlock(Request $request, string $user): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Vui lòng nhập lý do mở khóa.',
        ]);

        $target = User::query()->findOrFail($user);

        /** @var User $admin */
        $admin = $request->user();

        $this->lockService->unlock($target, $admin, $data['reason']);

        return response()->json([
            'message' => 'Mở khóa tài khoản thành công.',
        ]);
    }

    /**
     * GET /admin/users/{user}/lock-logs
     * Lịch sử khóa/mở khóa của user.
     */
    public function lockLogs(string $user): JsonResponse
    {
        $target = User::query()->findOrFail($user);

        $logs = $target->lockLogs()
            ->with('lockedBy:id,username,full_name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => UserLockLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
