<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Get list of conversations for the authenticated user
     */
    public function getConversations(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with([
            'participants.user:id,full_name,username,avatar_url',
        ])
        ->get();

        $formatted = $conversations->map(function ($conversation) use ($userId) {
            // Find the other participant in direct/venue chats
            $otherParticipant = $conversation->participants->first(function ($p) use ($userId) {
                return $p->user_id !== $userId;
            });
            $otherUser = $otherParticipant ? $otherParticipant->user : null;

            // Determine Title & Avatar
            $title = $conversation->title;
            $avatarUrl = null;

            if ($conversation->type === 'venue_contact' && $conversation->reference_id) {
                $venue = VenueCluster::find($conversation->reference_id);
                $title = $venue ? $venue->name : 'Sân đấu';
                $avatarUrl = $otherUser ? $otherUser->avatar_url : null;
            } else {
                $title = $title ?: ($otherUser ? $otherUser->full_name : 'Người dùng');
                $avatarUrl = $otherUser ? $otherUser->avatar_url : null;
            }

            // Get last message
            $lastMessage = Message::where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // Calculate unread messages
            $myParticipant = $conversation->participants->first(function ($p) use ($userId) {
                return $p->user_id === $userId;
            });
            $lastReadAt = $myParticipant ? $myParticipant->last_read_at : null;

            $unreadQuery = Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $userId);

            if ($lastReadAt) {
                $unreadQuery->where('created_at', '>', $lastReadAt);
            }
            $unreadCount = $unreadQuery->count();

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'reference_type' => $conversation->reference_type,
                'reference_id' => $conversation->reference_id,
                'title' => $title,
                'avatar_url' => $avatarUrl,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'full_name' => $otherUser->full_name,
                    'username' => $otherUser->username,
                ] : null,
                'last_message' => $lastMessage ? [
                    'content' => $lastMessage->content,
                    'created_at' => $lastMessage->created_at,
                    'sender_id' => $lastMessage->sender_id,
                ] : null,
                'unread_count' => $unreadCount,
                'last_message_at' => $conversation->last_message_at ? $conversation->last_message_at->toIso8601String() : null,
            ];
        });

        // Sort by last message time
        return response()->json($formatted->sortByDesc('last_message_at')->values());
    }

    /**
     * Get messages in a conversation
     */
    public function getMessages(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        $isParticipant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->with('sender:id,full_name,username,avatar_url')
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        // Check if other participant has read these messages
        $participants = ConversationParticipant::where('conversation_id', $conversationId)->get();

        return response()->json([
            'messages' => $messages,
            'participants' => $participants->map(function ($p) {
                return [
                    'user_id' => $p->user_id,
                    'last_read_at' => $p->last_read_at ? $p->last_read_at->toIso8601String() : null,
                ];
            }),
        ]);
    }

    /**
     * Send a message to a conversation
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        $isParticipant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $request->validate([
            'content' => 'required|string',
        ]);

        $message = DB::transaction(function () use ($conversationId, $userId, $request) {
            $now = now();
            $msg = Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'content' => $request->input('content'),
                'is_system' => false,
                'created_at' => $now,
            ]);

            Conversation::where('id', $conversationId)->update([
                'last_message_at' => $now,
            ]);

            ConversationParticipant::where('conversation_id', $conversationId)
                ->where('user_id', $userId)
                ->update([
                    'last_read_at' => $now,
                ]);

            return $msg;
        });

        return response()->json($message->load('sender:id,full_name,username,avatar_url'));
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead(Request $request, $conversationId)
    {
        $userId = $request->user()->id;

        ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update([
                'last_read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Search other users to chat
     */
    public function searchUsers(Request $request)
    {
        $query = $request->query('query');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $currentUser = $request->user();
        $users = User::where('id', '!=', $currentUser->id)
            ->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->with('roles')
            ->limit(100)
            ->get();

        $filtered = $users->filter(function ($targetUser) use ($currentUser) {
            return $this->canMessageEachOther($currentUser, $targetUser);
        })->take(15)->values();

        return response()->json($filtered->map(function ($u) {
            return [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'username' => $u->username,
                'avatar_url' => $u->avatar_url,
                'email' => $u->email,
            ];
        }));
    }

    /**
     * Start or fetch a conversation
     */
    public function startConversation(Request $request)
    {
        $currentUser = $request->user();
        $userId = $currentUser->id;
        $type = $request->input('type', 'direct');

        if ($type === 'direct') {
            $targetUserId = $request->input('user_id');
            if (!$targetUserId) {
                return response()->json(['message' => 'Mã người dùng là bắt buộc.'], 400);
            }
            if ($targetUserId === $userId) {
                return response()->json(['message' => 'Bạn không thể tự chat với chính mình.'], 400);
            }

            $targetUser = User::findOrFail($targetUserId);
            if (!$this->canMessageEachOther($currentUser, $targetUser)) {
                return response()->json(['message' => 'Bạn không có quyền nhắn tin với người dùng này.'], 403);
            }

            // Check if conversation already exists
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereHas('participants', function ($q) use ($targetUserId) {
                    $q->where('user_id', $targetUserId);
                })
                ->first();

            if ($existing) {
                return response()->json(['id' => $existing->id]);
            }

            // Create new direct conversation
            $conversation = DB::transaction(function () use ($userId, $targetUserId) {
                $now = now();
                $conv = Conversation::create([
                    'id' => (string) Str::uuid(),
                    'type' => 'direct',
                    'created_by' => $userId,
                    'last_message_at' => $now,
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $userId,
                    'last_read_at' => $now,
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $targetUserId,
                    'last_read_at' => null,
                ]);

                return $conv;
            });

            return response()->json(['id' => $conversation->id]);
        }

        if ($type === 'venue_contact') {
            $venueId = $request->input('venue_id');
            if (!$venueId) {
                return response()->json(['message' => 'Mã sân đấu là bắt buộc.'], 400);
            }

            $venue = VenueCluster::findOrFail($venueId);
            $ownerId = $venue->owner_id;

            if (!$ownerId) {
                return response()->json(['message' => 'Sân đấu này chưa có người quản lý.'], 400);
            }

            if ($ownerId === $userId) {
                return response()->json(['message' => 'Bạn là chủ sở hữu của sân đấu này.'], 400);
            }

            $owner = User::findOrFail($ownerId);
            if (!$this->canMessageEachOther($currentUser, $owner)) {
                return response()->json(['message' => 'Bạn không có quyền liên hệ với chủ sân này.'], 403);
            }

            // Check if venue contact conversation already exists
            $existing = Conversation::where('type', 'venue_contact')
                ->where('reference_type', 'venue_cluster')
                ->where('reference_id', $venueId)
                ->whereHas('participants', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->first();

            if ($existing) {
                return response()->json(['id' => $existing->id]);
            }

            // Create new venue contact conversation
            $conversation = DB::transaction(function () use ($userId, $ownerId, $venueId) {
                $now = now();
                $conv = Conversation::create([
                    'id' => (string) Str::uuid(),
                    'type' => 'venue_contact',
                    'reference_type' => 'venue_cluster',
                    'reference_id' => $venueId,
                    'created_by' => $userId,
                    'last_message_at' => $now,
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $userId,
                    'last_read_at' => $now,
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conv->id,
                    'user_id' => $ownerId,
                    'last_read_at' => null,
                ]);

                return $conv;
            });

            return response()->json(['id' => $conversation->id]);
        }

        return response()->json(['message' => 'Loại cuộc trò chuyện không hợp lệ.'], 400);
    }

    /**
     * Check if two users are allowed to message each other based on their roles
     */
    private function canMessageEachOther(User $userA, User $userB): bool
    {
        if ($userA->id === $userB->id) {
            return false;
        }

        $rolesA = $userA->roles->pluck('name')->toArray();
        $rolesB = $userB->roles->pluck('name')->toArray();

        // 1. Check if both are internal admin/staff
        $adminRoles = [
            'super_admin',
            'admin',
            'system_staff',
            'content_moderator',
            'complaint_handler',
            'venue_manager',
            'partner_manager',
            'booking_support',
            'finance_operator',
            'policy_manager',
            'staff_manager',
        ];

        $isAAdmin = !empty(array_intersect($rolesA, $adminRoles));
        $isBAdmin = !empty(array_intersect($rolesB, $adminRoles));

        if ($isAAdmin && $isBAdmin) {
            return true;
        }

        // Admin/internal staff cannot message anyone else (user or owner/staff)
        if ($isAAdmin || $isBAdmin) {
            return false;
        }

        // 2. Khách hàng (user) và Chủ sân (venue_owner) được nhắn với nhau
        $isAUser = in_array('user', $rolesA);
        $isBUser = in_array('user', $rolesB);
        $isAOwner = in_array('venue_owner', $rolesA);
        $isBOwner = in_array('venue_owner', $rolesB);

        if (($isAUser && $isBOwner) || ($isBUser && $isAOwner)) {
            return true;
        }

        // 3. Chủ sân (venue_owner) và Nhân viên của sân mình (venue_staff) được nhắn với nhau
        $isAStaff = in_array('venue_staff', $rolesA);
        $isBStaff = in_array('venue_staff', $rolesB);

        if (($isAOwner && $isBStaff) || ($isBOwner && $isAStaff)) {
            $ownerId = $isAOwner ? $userA->id : $userB->id;
            $staffId = $isAStaff ? $userA->id : $userB->id;

            return DB::table('venue_clusters')
                ->join('venue_staff_assignments', 'venue_clusters.id', '=', 'venue_staff_assignments.venue_cluster_id')
                ->where('venue_clusters.owner_id', $ownerId)
                ->where('venue_staff_assignments.user_id', $staffId)
                ->where('venue_staff_assignments.status', 'active')
                ->exists();
        }

        return false;
    }
}
