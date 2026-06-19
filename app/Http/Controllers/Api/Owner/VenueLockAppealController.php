<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Models\VenueLockAppeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VenueLockAppealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appeals = VenueLockAppeal::query()
            ->with('venueCluster:id,name,status,status_reason')
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['data' => $appeals]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'venue_cluster_id' => ['required', 'uuid', 'exists:venue_clusters,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $cluster = VenueCluster::findOrFail($data['venue_cluster_id']);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền khiếu nại cho cụm sân này.'], 403);
        }

        if ($cluster->status !== 'locked') {
            throw ValidationException::withMessages([
                'venue_cluster_id' => 'Cụm sân này hiện không bị khóa, không cần gửi yêu cầu liên hệ/kháng cáo.',
            ]);
        }

        $appeal = VenueLockAppeal::create([
            'venue_cluster_id' => $cluster->id,
            'owner_id' => $request->user()->id,
            'title' => trim($data['title']),
            'content' => trim($data['content']),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Gửi yêu cầu liên hệ thành công. Ban quản trị SportGo sẽ sớm phản hồi.',
            'data' => $appeal->load('venueCluster'),
        ], 201);
    }
}
