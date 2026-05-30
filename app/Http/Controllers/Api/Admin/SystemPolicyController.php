<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $policies = SystemPolicy::query()
            ->with(['createdBy:id,username,full_name', 'updatedBy:id,username,full_name'])
            ->when($request->filled('key'), fn ($query) => $query->where('key', $request->query('key')))
            ->orderBy('key')
            ->orderByDesc('version')
            ->get();

        return response()->json(['data' => $policies]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $policy = SystemPolicy::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Đã tạo chính sách hệ thống.',
            'data' => $policy->load(['createdBy:id,username,full_name', 'updatedBy:id,username,full_name']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $policy = SystemPolicy::query()
            ->with(['createdBy:id,username,full_name', 'updatedBy:id,username,full_name'])
            ->findOrFail($id);

        return response()->json(['data' => $policy]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $policy = SystemPolicy::query()->findOrFail($id);
        $data = $this->validated($request, $policy);

        $policy->update([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật chính sách hệ thống.',
            'data' => $policy->fresh(['createdBy:id,username,full_name', 'updatedBy:id,username,full_name']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $policy = SystemPolicy::query()->findOrFail($id);
        $policy->update(['is_active' => false]);

        return response()->json(['message' => 'Đã tắt chính sách.']);
    }

    private function validated(Request $request, ?SystemPolicy $policy = null): array
    {
        $versionUnique = Rule::unique('system_policies', 'version')
            ->where(fn ($query) => $query->where('key', $request->input('key', $policy?->key)));

        if ($policy) {
            $versionUnique->ignore($policy->id);
        }

        return $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'version' => ['required', 'integer', 'min:1', $versionUnique],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', Rule::in(['general', 'refund', 'booking', 'moderation'])],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ]);
    }
}
