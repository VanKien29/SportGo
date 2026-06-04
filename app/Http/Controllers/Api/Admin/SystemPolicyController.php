<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SystemPolicyController extends Controller
{
    /**
     * Hiển thị danh sách các chính sách hệ thống.
     */ 
    public function index()
    {
        $policies = SystemPolicy::with(['createdBy', 'updatedBy'])
            ->orderBy('key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('key');

        return response()->json([
            'status' => 'success',
            'data' => $policies
        ]);
    }

    /**
     * Lưu một chính sách mới vào cơ sở dữ liệu.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:general,refund,booking,moderation',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Tìm phiên bản cao nhất hiện tại của key này
        $latestVersion = SystemPolicy::where('key', $request->key)->max('version') ?? 0;

        $policy = SystemPolicy::create([
            'key' => $request->key,
            'version' => $latestVersion + 1,
            'title' => $request->title,
            'content' => $request->content,
            'type' => $request->type,
            'is_active' => $request->is_active ?? true,
            'effective_from' => $request->effective_from ?? now(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo chính sách mới thành công.',
            'data' => $policy
        ], 201);
    }

    /**
     * Hiển thị chi tiết một chính sách.
     */
    public function show($id)
    {
        $policy = SystemPolicy::with(['createdBy', 'updatedBy'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $policy
        ]);
    }

    /**
     * Cập nhật chính sách.
     * Lưu ý: Việc cập nhật sẽ tạo ra một phiên bản mới thay vì ghi đè nếu Key được giữ nguyên.
     */
    public function update(Request $request, $id)
    {
        $policy = SystemPolicy::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Logic: Tạo phiên bản mới khi có cập nhật
        
        $newVersion = SystemPolicy::where('key', $policy->key)->max('version') + 1;

        $newPolicy = SystemPolicy::create([
            'key' => $policy->key,
            'version' => $newVersion,
            'title' => $request->title,
            'content' => $request->content,
            'type' => $policy->type,
            'is_active' => $request->is_active ?? true,
            'effective_from' => $request->effective_from ?? now(),
            'created_by' => $policy->created_by,
            'updated_by' => Auth::id(),
        ]);

        // Hủy kích hoạt phiên bản cũ nếu cần
        $policy->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật chính sách thành công (Phiên bản ' . $newVersion . ').',
            'data' => $newPolicy
        ]);
    }

    /**
     * Xóa chính sách khỏi hệ thống.
     */
    public function destroy($id)
    {
        $policy = SystemPolicy::findOrFail($id);
        $policy->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa chính sách thành công.'
        ]);
    }
}
