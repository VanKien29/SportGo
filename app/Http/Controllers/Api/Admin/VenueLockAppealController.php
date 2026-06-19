<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\VenueLockAppeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VenueLockAppealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'resolved', 'rejected'])],
            'venue_cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
        ]);

        $appeals = VenueLockAppeal::query()
            ->with(['venueCluster:id,name,status,status_reason', 'owner:id,username,full_name,email,phone', 'repliedBy:id,username,full_name'])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($data['venue_cluster_id'] ?? null, fn ($query, string $venueClusterId) => $query->where('venue_cluster_id', $venueClusterId))
            ->latest()
            ->paginate(20);

        return response()->json($appeals);
    }

    public function show(string $id): JsonResponse
    {
        $appeal = VenueLockAppeal::query()
            ->with(['venueCluster', 'owner', 'repliedBy'])
            ->findOrFail($id);

        return response()->json(['data' => $appeal]);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $appeal = VenueLockAppeal::findOrFail($id);

        $data = $request->validate([
            'reply_content' => ['required', 'string', 'max:5000'],
            'decision' => ['required', Rule::in(['resolved', 'rejected'])],
        ]);

        $appeal->update([
            'status' => $data['decision'],
            'reply_content' => trim($data['reply_content']),
            'replied_by' => $request->user()->id,
            'replied_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã gửi phản hồi cho khiếu nại thành công.',
            'data' => $appeal->load(['venueCluster', 'owner', 'repliedBy']),
        ]);
    }
}
