<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Services\WorkCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkCenterController extends Controller
{
    public function __construct(private readonly WorkCenterService $workCenter)
    {
    }

    public function admin(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workCenter->forAdmin($request->user())]);
    }

    public function owner(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workCenter->forOwner($request->user())]);
    }

    public function markNotificationRead(Request $request, int $notificationId): JsonResponse
    {
        if (! Schema::hasTable('notifications')) {
            return response()->json(['message' => 'Thông báo không tồn tại.'], 404);
        }

        $updated = DB::table('notifications')
            ->where('id', $notificationId)
            ->where('user_id', $request->user()->id)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if (! $updated) {
            $exists = DB::table('notifications')
                ->where('id', $notificationId)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (! $exists) {
                return response()->json(['message' => 'Thông báo không tồn tại.'], 404);
            }
        }

        return response()->json(['message' => 'Đã đánh dấu thông báo là đã đọc.']);
    }
}
