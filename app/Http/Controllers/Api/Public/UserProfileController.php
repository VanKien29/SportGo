<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PlayerPost;
use App\Models\VenuePost;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    /**
     * Lấy thông tin cơ bản của User
     */
    public function show(string $id): JsonResponse
    {
        $user = User::select('id', 'full_name', 'username', 'avatar_url', 'created_at')
            ->findOrFail($id);

        $totalMatchmakingPosts = PlayerPost::where('author_id', $id)->count();
        $totalCommunityPosts = VenuePost::where('author_id', $id)->where('status', 'published')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'stats' => [
                    'total_matchmaking_posts' => $totalMatchmakingPosts,
                    'total_community_posts' => $totalCommunityPosts,
                ]
            ]
        ]);
    }
}
