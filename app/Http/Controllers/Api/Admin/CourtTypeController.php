<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourtType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtTypeController extends Controller
{
    private const SPORT_ICON_RULES = [
        'badminton' => ['cầu lông', 'badminton'],
        'pickleball' => ['pickleball'],
        'football' => ['bóng đá', 'football', 'futsal'],
        'basketball' => ['bóng rổ', 'basketball'],
        'tennis' => ['tennis', 'quần vợt'],
        'volleyball' => ['bóng chuyền', 'volleyball'],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = CourtType::query()->with('parent')->withCount('children');

        if ($request->boolean('active_only') || ! $request->is('api/admin/*')) {
            $query->where('is_active', true);
        }

        $courtTypes = $query->latest()->get();

        return response()->json(['data' => $courtTypes]);
    }

    public function show(int $id): JsonResponse
    {
        $courtType = CourtType::query()
            ->with('parent')
            ->withCount('children')
            ->findOrFail($id);

        return response()->json(['data' => $courtType]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:court_types,name'],
            'parent_id' => ['nullable', 'exists:court_types,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon_key' => ['nullable', 'string', 'in:activity,badminton,pickleball,football,basketball,tennis,volleyball'],
            'player_count' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'default_layout_w' => ['nullable', 'numeric', 'min:0'],
            'default_layout_h' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['icon_key'] = $this->resolveIconKey($data);

        $courtType = CourtType::query()->create($data);

        return response()->json([
            'message' => 'Tạo loại sân thành công.',
            'data' => $courtType->load('parent'),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $courtType = CourtType::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:court_types,name,'.$id],
            'parent_id' => ['nullable', 'exists:court_types,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon_key' => ['nullable', 'string', 'in:activity,badminton,pickleball,football,basketball,tennis,volleyball'],
            'player_count' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'default_layout_w' => ['nullable', 'numeric', 'min:0'],
            'default_layout_h' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['icon_key'] = $this->resolveIconKey($data);

        $courtType->update($data);

        return response()->json([
            'message' => 'Cập nhật loại sân thành công.',
            'data' => $courtType->load('parent'),
        ]);
    }

    private function inferIconKey(string $name): ?string
    {
        $normalized = mb_strtolower(trim($name));

        foreach (self::SPORT_ICON_RULES as $iconKey => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $iconKey;
                }
            }
        }

        return null;
    }

    private function resolveIconKey(array $data): string
    {
        $requestedIcon = $data['icon_key'] ?? null;
        if ($requestedIcon && $requestedIcon !== 'activity') {
            return $requestedIcon;
        }

        if (! empty($data['parent_id'])) {
            $parentIcon = CourtType::query()->whereKey($data['parent_id'])->value('icon_key');
            if ($parentIcon && $parentIcon !== 'activity') {
                return $parentIcon;
            }
        }

        return $this->inferIconKey($data['name']) ?? 'activity';
    }

    public function destroy(int $id): JsonResponse
    {
        $courtType = CourtType::query()->findOrFail($id);
        $courtType->delete();

        return response()->json([
            'message' => 'Xóa loại sân thành công.',
        ]);
    }
}
