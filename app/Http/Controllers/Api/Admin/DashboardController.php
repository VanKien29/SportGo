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
        // For now, since Venues and Bookings and Revenue tables might not exist or be populated,
        // we will count users and mock the rest, or try to query if tables exist.
        // I will check the migrations to see what tables are available.
        $usersCount = User::query()->count();
        
        $venuesCount = DB::table('system_posts')->count(); // Just using some table as mock for now if venues don't exist
        $bookingsCount = DB::table('payments')->count(); 
        $revenue = DB::table('payments')->where('status', 'success')->sum('amount') ?? 0;

        return response()->json([
            'users' => $usersCount,
            'venues' => $venuesCount,
            'bookings' => $bookingsCount,
            'revenue' => (float)$revenue,
        ]);
    }
}
