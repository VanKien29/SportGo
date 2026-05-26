<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Currently just returning dummy data mixed with real query formats
        // since we haven't built the full booking/venue logic yet.
        $bookingsCount = DB::table('payments')->count();
        $revenue = DB::table('payments')->where('status', 'success')->sum('amount') ?? 0;
        
        return response()->json([
            'bookings' => $bookingsCount,
            'revenue' => (float)$revenue,
            'rating' => 4.8, // Static for now until reviews logic is built
        ]);
    }
}
