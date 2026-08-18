<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['data' => [], 'unread_count' => 0]);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 5), 50);
        $notifications = $user->notifications()
            ->when($request->filled('type'), function ($query) use ($request): void {
                $type = $request->string('type')->toString();
                $groups = ['booking', 'refund', 'matchmaking', 'complaint', 'wallet', 'report', 'membership', 'post'];
                if (in_array($type, $groups, true)) {
                    $query->where('type', 'like', $type . '%');
                } else {
                    $query->where('type', $type);
                }
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $keyword = '%' . $request->string('search')->toString() . '%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $keyword)->orWhere('body', 'like', $keyword));
            })
            ->when($request->has('read') && $request->input('read') !== '', fn ($query) => $query->where('is_read', filter_var($request->input('read'), FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(function ($notif) {
                $actionUrl = $this->targetUrl($notif);

                return [
                    'id' => $notif->id,
                    'type' => $notif->type,
                    'title' => $notif->title,
                    'body' => $notif->body,
                    'reference_type' => $notif->reference_type,
                    'reference_id' => $notif->reference_id,
                    'data' => $notif->data,
                    'action_url' => $actionUrl,
                    'is_read' => $notif->is_read,
                    'created_at' => $notif->created_at,
                ];
            });

        $unreadCount = $user->notifications()->where('is_read', false)->count();

        return response()->json([
            'data' => $notifications->items(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'total' => $notifications->total(),
            'unread_count' => $unreadCount,
        ]);
    }

    private function targetUrl(object $notification): ?string
    {
        $data = is_array($notification->data) ? $notification->data : [];
        foreach ([$data['action_url'] ?? null, $data['url'] ?? null, $data['link'] ?? null] as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, '/')) {
                return $candidate;
            }
        }

        $referenceType = strtolower(str_replace('\\', '/', (string) $notification->reference_type));
        $type = strtolower((string) $notification->type);
        $referenceId = $notification->reference_id;

        if (! $referenceId && ! empty($data['booking_id'])) {
            $referenceId = $data['booking_id'];
        }

        if ($referenceId && (str_contains($referenceType, 'booking') || str_contains($type, 'booking') || (str_contains($type, 'payment') && ! empty($data['booking_id'])))) {
            return '/booking/' . $referenceId;
        }

        if ($referenceId && (str_contains($referenceType, 'refund') || str_contains($type, 'refund'))) {
            return '/refunds/' . $referenceId;
        }

        if ($referenceId && (str_contains($referenceType, 'complaint') || str_contains($type, 'complaint'))) {
            return '/complaints/' . $referenceId;
        }

        if ($referenceId && str_contains($referenceType, 'partner_application')) {
            return '/partner-application/' . $referenceId;
        }

        if ($referenceId && str_contains($referenceType, 'player_post')) {
            if (str_contains($type, 'participant') || str_contains($type, 'matchmaking_request')) {
                return '/matchmaking-requests/' . $referenceId;
            }

            return '/matchmaking-posts/' . $referenceId . '/manage';
        }

        if (str_contains($type, 'post_like') || str_contains($type, 'post_comment') || str_contains($type, 'comment_reply')) {
            return ! empty($data['slug']) ? '/community/' . $data['slug'] : '/community';
        }

        if (str_contains($type, 'post_approved')) {
            return ! empty($data['slug']) && str_contains($referenceType, 'system_post')
                ? '/news/' . $data['slug']
                : '/community';
        }

        if (str_contains($type, 'wallet') || str_contains($referenceType, 'wallet')) {
            return '/wallet';
        }

        if (str_contains($type, 'membership')) {
            return '/vip-membership';
        }

        return '/notifications';
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notification = $user->notifications()->find($id);
        if ($notification && !$notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user->notifications()->where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'All marked as read']);
    }
}
