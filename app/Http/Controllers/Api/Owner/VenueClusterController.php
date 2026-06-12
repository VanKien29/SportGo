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
        $ownedClusterIds = VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->pluck('id');

        $assignedClusterIds = \Illuminate\Support\Facades\DB::table('venue_staff_assignments')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        $clusters = VenueCluster::query()
            ->with(['media'])
            ->whereIn('id', $ownedClusterIds->merge($assignedClusterIds)->unique()->values())
            ->latest()
            ->get();

        return response()->json(['data' => $clusters]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->with(['venueCourts.courtType', 'media', 'bookingConfig'])
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
            'province' => ['required', 'string', 'max:255'],
            'ward' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'map_url' => ['nullable', 'url', 'max:2000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'amenities' => ['nullable', 'array'],
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . substr($id, 0, 8);
        $amenityNames = $data['amenities'] ?? [];

        // Find matching active amenities
        $activeAmenities = \App\Models\Amenity::whereIn('name', $amenityNames)
            ->where('status', 'active')
            ->get();

        $syncData = [];
        foreach ($activeAmenities as $amenity) {
            $syncData[$amenity->id] = [
                'is_visible' => true,
                'description' => null,
            ];
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($cluster, $data, $syncData) {
            $cluster->update($data);
            $cluster->amenityCatalog()->sync($syncData);
        });

        return response()->json([
            'message' => 'Cập nhật cụm sân thành công.',
            'data' => $cluster->fresh(['media']),
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
                    'data' => [
                        'latitude' => (float)$matches[1],
                        'longitude' => (float)$matches[2],
                        'final_url' => $finalUrl,
                    ]
                ]);
            }

            // 2. Dạng !3dlat!4dlng
            if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $finalUrl, $matches)) {
                return response()->json([
                    'data' => [
                        'latitude' => (float)$matches[1],
                        'longitude' => (float)$matches[2],
                        'final_url' => $finalUrl,
                    ]
                ]);
            }

            // 3. Dạng q=lat,lng
            if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $finalUrl, $matches)) {
                return response()->json([
                    'data' => [
                        'latitude' => (float)$matches[1],
                        'longitude' => (float)$matches[2],
                        'final_url' => $finalUrl,
                    ]
                ]);
            }

            // Trả về final_url để client-side tự parse tiếp
            return response()->json([
                'data' => [
                    'latitude' => null,
                    'longitude' => null,
                    'final_url' => $finalUrl,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi kết nối khi phân giải link map: ' . $e->getMessage()], 500);
        }
    }

    public function uploadMedia(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền upload ảnh cho cụm sân này.'], 403);
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // tối đa 5MB
        ]);

        $path = $request->file('image')->store('clusters', 'public');

        $media = \App\Models\Media::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'mediable_type' => VenueCluster::class,
            'mediable_id' => $cluster->id,
            'collection' => 'gallery',
            'file_name' => $request->file('image')->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $request->file('image')->getClientMimeType(),
            'file_size' => $request->file('image')->getSize(),
        ]);

        return response()->json([
            'message' => 'Tải lên hình ảnh thành công.',
            'data' => $media,
        ]);
    }

    public function deleteMedia(Request $request, string $clusterId, string $mediaId): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($clusterId);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa ảnh của cụm sân này.'], 403);
        }

        $media = \App\Models\Media::where('mediable_type', VenueCluster::class)
            ->where('mediable_id', $clusterId)
            ->findOrFail($mediaId);

        // Xóa file vật lý
        \Illuminate\Support\Facades\Storage::disk('public')->delete($media->file_path);

        // Xóa bản ghi DB
        $media->delete();

        return response()->json([
            'message' => 'Xóa hình ảnh thành công.',
        ]);
    }
}
