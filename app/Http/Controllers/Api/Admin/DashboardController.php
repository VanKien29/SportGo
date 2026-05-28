<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usersCount = User::query()->count();
        $venuesCount = DB::table('venue_clusters')->count();
        $bookingsCount = DB::table('bookings')->count();
        $revenue = DB::table('payments')->where('status', 'paid')->sum('amount') ?? 0;

        return response()->json([
            'users' => $usersCount,
            'venues' => $venuesCount,
            'bookings' => $bookingsCount,
            'revenue' => (float)$revenue,
        ]);
    }
}
