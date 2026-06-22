<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\CourtTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtTypeRequestController extends Controller
{
    /**
     * Gửi yêu cầu thêm loại sân mới.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'parent_id'    => ['nullable', 'integer', 'exists:court_types,id'],
            'player_count' => ['required', 'integer', 'min:1'],
            'description'  => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required'         => 'Vui lòng nhập tên loại sân.',
            'name.max'              => 'Tên loại sân không được vượt quá 100 ký tự.',
            'parent_id.exists'      => 'Loại sân cha không tồn tại.',
            'player_count.required' => 'Vui lòng nhập số người chơi tham khảo.',
            'player_count.integer'  => 'Số người chơi phải là số nguyên.',
            'player_count.min'      => 'Số người chơi phải tối thiểu là 1.',
            'description.max'       => 'Mô tả lý do không được vượt quá 1000 ký tự.',
        ]);

        $courtTypeRequest = CourtTypeRequest::create([
            'name'         => $validated['name'],
            'parent_id'    => $validated['parent_id'] ?? null,
            'player_count' => $validated['player_count'],
            'description'  => $validated['description'] ?? null,
            'requested_by' => $request->user()->id,
            'status'       => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gửi yêu cầu thêm loại sân thành công. Vui lòng chờ admin duyệt.',
            'data'    => $courtTypeRequest->load(['parent']),
        ], 201);
    }
}
