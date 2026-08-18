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
        'community_post' => \App\Models\CommunityPost::class,
        'community_post_comment' => \App\Models\CommunityPostComment::class,
        'user' => User::class,
        'venue' => VenueCluster::class,
        'venue_cluster' => VenueCluster::class,
    ];

    public function index(Request $request)
    {
        $reports = Report::query()
            ->where('reporter_id', $request->user()->id)
            ->with(['evidence', 'reviewedBy:id,username,full_name'])
            ->latest('created_at')
            ->paginate(12);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (Report $report) => $this->clientPayload($report))->values(),
            'current_page' => $reports->currentPage(),
            'last_page' => $reports->lastPage(),
            'total' => $reports->total(),
        ]);
    }

    public function show(Request $request, string $id)
    {
        $report = Report::query()
            ->where('reporter_id', $request->user()->id)
            ->with(['evidence', 'reviewedBy:id,username,full_name'])
            ->findOrFail($id);

        return response()->json(['data' => $this->clientPayload($report)]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'target_type' => ['required', 'string', 'in:post,comment,venue,venue_cluster,user,player_post,venue_post,community_post,community_post_comment'],
            'target_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'in:spam,offensive,fake,harassment,other,fraud,inappropriate_content'],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence_image' => ['nullable', 'image', 'max:5120'], // max 5MB
        ]);

        $reason = match ($request->reason) {
            'fraud' => 'fake',
            'inappropriate_content' => 'offensive',
            default => $request->reason,
        };

        $targetType = $request->target_type;
        $targetId = $request->target_id;

        $modelClass = self::TARGET_TYPES[$targetType] ?? null;
        if (!$modelClass) {
            return response()->json(['message' => 'Loại đối tượng không hợp lệ.'], 422);
        }

        $modelClass::query()->findOrFail($targetId);

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
            'reason' => $reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        if ($request->hasFile('evidence_image')) {
            $thumbnail = $request->file('evidence_image');
            $manager = \Intervention\Image\ImageManager::usingDriver(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->decodePath($thumbnail->getPathname());
            
            $filename = uniqid('report_', true) . '.webp';
            $path = 'reports/' . $filename;
            
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists('reports')) {
                \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('reports');
            }
            
            $image->save(storage_path('app/public/' . $path), 80);

            $report->evidence()->create([
                'collection' => 'evidence_image',
                'file_name' => $thumbnail->getClientOriginalName() . '.webp',
                'file_path' => $path,
                'mime_type' => 'image/webp',
                'file_size' => filesize(storage_path('app/public/' . $path)),
            ]);
        }

        return response()->json([
            'message' => 'Báo cáo của bạn đã được ghi nhận. Cảm ơn bạn đã đóng góp cho cộng đồng.',
            'data' => $report->load('evidence')
        ], 201);
    }

    private function clientPayload(Report $report): array
    {
        return [
            'id' => $report->id,
            'target_type' => array_search($report->reportable_type, self::TARGET_TYPES, true) ?: class_basename($report->reportable_type),
            'target_id' => $report->reportable_id,
            'reason' => $report->reason,
            'description' => $report->description,
            'status' => $report->status,
            'action_taken' => $report->action_taken,
            'action_note' => $report->action_note,
            'reviewed_by' => $report->reviewedBy?->full_name ?? $report->reviewedBy?->username,
            'reviewed_at' => $report->reviewed_at,
            'created_at' => $report->created_at,
            'evidence' => $report->evidence->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'file_path' => $media->file_path,
            ])->values(),
        ];
    }
}
