<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VenueClusterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $clusters = VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['data' => $clusters]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->with(['venueCourts.courtType'])
            ->where('owner_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['data' => $cluster]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::query()->findOrFail($id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa cụm sân này.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone_contact' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'map_url' => ['nullable', 'url', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'amenities' => ['nullable', 'array'],
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . substr($id, 0, 8);

        $cluster->update($data);

        return response()->json([
            'message' => 'Cập nhật cụm sân thành công.',
            'data' => $cluster,
        ]);
    }

    public function resolveMapUrl(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        $url = $request->input('url');

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_exec($ch);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            // Tìm tọa độ từ URL sau khi redirect
            // 1. Dạng @lat,lng
            if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $finalUrl, $matches)) {
                return response()->json([
                    'latitude' => (float)$matches[1],
                    'longitude' => (float)$matches[2],
                ]);
            }

            // 2. Dạng !3dlat!4dlng
            if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $finalUrl, $matches)) {
                return response()->json([
                    'latitude' => (float)$matches[1],
                    'longitude' => (float)$matches[2],
                ]);
            }

            // 3. Dạng q=lat,lng
            if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $finalUrl, $matches)) {
                return response()->json([
                    'latitude' => (float)$matches[1],
                    'longitude' => (float)$matches[2],
                ]);
            }

            return response()->json(['message' => 'Không thể trích xuất tọa độ từ liên kết này. Vui lòng kiểm tra lại.'], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi kết nối khi phân giải link map: ' . $e->getMessage()], 500);
        }
    }
}
