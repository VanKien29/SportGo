<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\AffiliateProduct;
use App\Models\VenueCluster;
use Illuminate\Http\JsonResponse;

class PublicAffiliateProductController extends Controller
{
    /**
     * Lấy danh sách sản phẩm tiếp thị liên kết đang hoạt động của cụm sân.
     */
    public function index(string $clusterId): JsonResponse
    {
        $cluster = VenueCluster::query()
            ->where('status', 'active')
            ->findOrFail($clusterId);

        $products = AffiliateProduct::query()
            ->where('venue_cluster_id', $cluster->id)
            ->where('is_active', true)
            ->latest()
            ->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Ghi nhận lượt click sản phẩm tiếp thị liên kết.
     */
    public function trackClick(string $id): JsonResponse
    {
        $product = AffiliateProduct::query()
            ->where('is_active', true)
            ->findOrFail($id);

        $product->increment('click_count');

        return response()->json([
            'message' => 'Ghi nhận click thành công.',
            'click_count' => $product->click_count,
        ]);
    }
}
