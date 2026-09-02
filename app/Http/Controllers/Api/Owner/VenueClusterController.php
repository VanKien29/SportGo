<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\VenueCluster;
use App\Services\Partner\PartnerMapResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VenueClusterController extends Controller
{
    public function __construct(private readonly PartnerMapResolver $maps)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $clusterIds = $request->boolean('owned_only')
            ? $this->ownedClusterIds($request)
            : $this->accessibleClusterIds($request);

        if ($request->boolean('compact')) {
            $clusters = VenueCluster::query()
                ->select(['id', 'name', 'status', 'status_reason'])
                ->with(['accessRestrictions' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where(function ($restrictionQuery): void {
                        $restrictionQuery->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->orderByRaw("CASE WHEN access_mode = 'blocked' THEN 0 ELSE 1 END")
                    ->orderByDesc('starts_at')])
                ->withCount(['venueCourts as court_count'])
                ->whereIn('id', $clusterIds)
                ->orderBy('name')
                ->get()
                ->map(fn (VenueCluster $cluster): VenueCluster => $this->attachAccessState($cluster));

            return response()->json(['data' => $clusters]);
        }

        $clusters = VenueCluster::query()
            ->with([
                'media',
                'amenityCatalog',
                'accessRestrictions' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where(function ($restrictionQuery): void {
                        $restrictionQuery->whereNull('ends_at')->orWhere('ends_at', '>', now());
                    })
                    ->orderByRaw("CASE WHEN access_mode = 'blocked' THEN 0 ELSE 1 END")
                    ->orderByDesc('starts_at'),
            ])
            ->withCount(['venueCourts as court_count'])
            ->whereIn('id', $clusterIds)
            ->latest()
            ->get()
            ->map(fn (VenueCluster $cluster): VenueCluster => $this->attachAccessState($cluster));

        return response()->json(['data' => $clusters]);
    }

    private function accessibleClusterIds(Request $request)
    {
        $ownedClusterIds = $this->ownedClusterIds($request);

        $assignedClusterIds = \Illuminate\Support\Facades\DB::table('venue_staff_assignments')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        return $ownedClusterIds->merge($assignedClusterIds)->unique()->values();
    }

    private function attachAccessState(VenueCluster $cluster): VenueCluster
    {
        $restriction = $cluster->accessRestrictions->first();
        $cluster->setAttribute('access_restriction', $restriction ? [
            'id' => $restriction->id,
            'restriction_type' => $restriction->restriction_type,
            'access_mode' => $restriction->access_mode,
            'reason' => $restriction->reason,
            'starts_at' => $restriction->starts_at?->toISOString(),
            'ends_at' => $restriction->ends_at?->toISOString(),
        ] : null);

        return $cluster;
    }

    private function ownedClusterIds(Request $request)
    {
        return VenueCluster::query()
            ->where('owner_id', $request->user()->id)
            ->pluck('id');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->with(['venueCourts.courtType', 'media', 'bookingConfig', 'amenityCatalog'])
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
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:2000'],
            'phone_contact'        => ['required', 'string', 'max:20'],
            'amenities'            => ['nullable', 'array'],
            'amenity_descriptions' => ['nullable', 'array'],
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . substr($id, 0, 8);
        $amenityNames = $data['amenities'] ?? [];
        $amenityDescriptions = $data['amenity_descriptions'] ?? [];

        // Find matching active amenities
        $activeAmenities = \App\Models\Amenity::whereIn('name', $amenityNames)
            ->where('status', 'active')
            ->get();

        $syncData = [];
        foreach ($activeAmenities as $amenity) {
            $syncData[$amenity->id] = [
                'is_visible' => true,
                'description' => $amenityDescriptions[$amenity->name] ?? null,
            ];
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($cluster, $data, $syncData) {
            $updateData = collect($data)->except(['amenity_descriptions'])->toArray();
            $cluster->update($updateData);
            $cluster->amenityCatalog()->sync($syncData);
        });

        return response()->json([
            'message' => 'Cập nhật cụm sân thành công.',
            'data' => $cluster->fresh(['media', 'amenityCatalog']),
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

    public function reverseMap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'data' => $this->maps->reverse((float) $data['latitude'], (float) $data['longitude']),
        ]);
    }

    public function uploadMedia(Request $request, string $id): JsonResponse
    {
        $cluster = VenueCluster::findOrFail($id);

        if ($cluster->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Bạn không có quyền upload ảnh cho cụm sân này.'], 403);
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // tối đa 5MB
        ], [
            'image.required' => 'Vui lòng chọn hình ảnh để tải lên.',
            'image.image'    => 'File tải lên phải là một hình ảnh.',
            'image.mimes'    => 'Hình ảnh phải có định dạng jpeg, png, jpg hoặc webp.',
            'image.max'      => 'Kích thước hình ảnh tối đa là 5MB.',
            'image.uploaded' => 'Tải lên hình ảnh thất bại. Vui lòng kiểm tra lại dung lượng hoặc định dạng file.',
        ]);

        $path = $request->file('image')->store('clusters', 'public');

        $media = \App\Models\Media::create([
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
