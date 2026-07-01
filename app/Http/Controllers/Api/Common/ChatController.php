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

        $currentUserId = $request->user()->id;
        $users = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(15)
            ->get(['id', 'full_name', 'username', 'avatar_url', 'email']);

        return response()->json($users);
    }

    /**
     * Start or fetch a conversation
     */
    public function startConversation(Request $request)
    {
        $userId = $request->user()->id;
        $type = $request->input('type', 'direct');

        if ($type === 'direct') {
            $targetUserId = $request->input('user_id');
            if (!$targetUserId) {
                return response()->json(['message' => 'Mã người dùng là bắt buộc.'], 400);
            }
            if ($targetUserId === $userId) {
                return response()->json(['message' => 'Bạn không thể tự chat với chính mình.'], 400);
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
}
