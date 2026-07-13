<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class SystemProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => SystemSetting::profilePayload(),
        ]);
    }
}
