<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use App\Models\User;
use App\Models\PlayerPost;
use App\Services\CommunityAuthorBadgeService;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    public function __construct(private CommunityAuthorBadgeService $authorBadges) {}

    /**
     * Lấy thông tin cơ bản của User
     */
    public function show(string $id): JsonResponse
    {
        $user = User::select('id', 'full_name', 'username', 'avatar_url', 'created_at')
            ->findOrFail($id);
        $user->setAttribute(
            'author_badges',
            $this->authorBadges->lookup([$user->id])[(string) $user->id] ?? []
        );

        $totalMatchmakingPosts = PlayerPost::where('author_id', $id)->count();
        $totalCommunityPosts = CommunityPost::where('author_id', $id)->where('status', 'published')->count();

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
