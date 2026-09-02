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
        $user = User::select('id', 'full_name', 'username', 'avatar_url', 'cover_image_url', 'created_at')
            ->findOrFail($id);

        // Badges and counters are optional profile decorations. A missing or
        // temporarily unavailable auxiliary table must not turn the whole
        // public profile into a 500 response.
        try {
            $user->setAttribute(
                'author_badges',
                $this->authorBadges->lookup([$user->id])[(string) $user->id] ?? []
            );
        } catch (\Throwable $exception) {
            report($exception);
            $user->setAttribute('author_badges', []);
        }

        $totalMatchmakingPosts = 0;
        try {
            $totalMatchmakingPosts = PlayerPost::where('author_id', $id)->count();
        } catch (\Throwable $exception) {
            report($exception);
        }

        $totalCommunityPosts = 0;
        try {
            $totalCommunityPosts = CommunityPost::where('author_id', $id)
                ->where('status', 'published')
                ->count();
        } catch (\Throwable $exception) {
            report($exception);
        }

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
