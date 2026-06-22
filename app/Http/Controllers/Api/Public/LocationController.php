<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\VnProvince;
use App\Models\VnWard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Lấy danh sách tỉnh/thành phố.
     */
    public function provinces(): JsonResponse
    {
        $provinces = VnProvince::query()
            ->orderBy('name', 'asc')
            ->get(['code', 'name']);

        return response()->json(['data' => $provinces]);
    }

    /**
     * Lấy danh sách xã/phường của một tỉnh/thành phố.
     */
    public function wards(Request $request): JsonResponse
    {
        $provinceCode = $request->query('province_code');

        if (!$provinceCode) {
            return response()->json(['message' => 'Vui lòng cung cấp mã tỉnh/thành phố (province_code).'], 400);
        }

        $wards = VnWard::query()
            ->where('province_code', $provinceCode)
            ->orderBy('name', 'asc')
            ->get(['code', 'name']);

        return response()->json(['data' => $wards]);
    }
}
