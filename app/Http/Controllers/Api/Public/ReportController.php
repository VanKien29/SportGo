<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use App\Models\VenuePost;
use App\Models\VenuePostComment;
use App\Models\PlayerPost;
use App\Models\User;
use App\Models\VenueCluster;

class ReportController extends Controller
{
    private const TARGET_TYPES = [
        'post' => VenuePost::class,
        'comment' => VenuePostComment::class,
        'venue_post' => VenuePost::class,
        'player_post' => PlayerPost::class,
        'user' => User::class,
        'venue' => VenueCluster::class,
    ];

    public function store(Request $request)
    {
        $request->validate([
            'target_type' => ['required', 'string', 'in:post,comment,venue,user,player_post,venue_post'],
            'target_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'in:spam,offensive,fake,harassment,other'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $targetType = $request->target_type;
        $targetId = $request->target_id;

        $modelClass = self::TARGET_TYPES[$targetType] ?? null;
        if (!$modelClass) {
            return response()->json(['message' => 'Loại đối tượng không hợp lệ.'], 400);
        }

        // Check if user already reported this target recently
        $existingReport = Report::where('reporter_id', $request->user()->id)
            ->where('reportable_type', $modelClass)
            ->where('reportable_id', $targetId)
            ->where('status', 'pending')
            ->first();

        if ($existingReport) {
            return response()->json(['message' => 'Bạn đã báo cáo nội dung này rồi. Vui lòng chờ quản trị viên xử lý.'], 422);
        }

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $targetId,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Báo cáo của bạn đã được ghi nhận. Cảm ơn bạn đã đóng góp cho cộng đồng.',
            'data' => $report
        ], 201);
    }
}
