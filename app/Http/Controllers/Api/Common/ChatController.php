<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Booking;
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
            'participants.user:id,full_name,username,avatar_url,email,phone',
        ])
        ->get();

        $formatted = $conversations->map(function ($conversation) use ($userId) {
            // Find the other participant in direct/venue chats
            $otherParticipant = $conversation->participants->first(function ($p) use ($userId) {
                return $p->user_id !== $userId;
            });
            $otherUser = $otherParticipant ? $otherParticipant->user : null;

            if ($conversation->type === 'direct') {
                if (!$otherUser) {
                    $title = 'Tin nhắn đã lưu';
                    $avatarUrl = null;
                } else {
                    $title = $otherUser->full_name;
                    $avatarUrl = $otherUser->avatar_url;
                }
            } elseif ($conversation->type === 'venue_contact' && $conversation->reference_id) {
                $venue = VenueCluster::find($conversation->reference_id);
                $title = $venue ? $venue->name : 'Sân đấu';
                $avatarUrl = $otherUser ? $otherUser->avatar_url : null;
            } else {
                $title = $conversation->title ?: ($otherUser ? $otherUser->full_name : 'Người dùng');
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
                    'avatar_url' => $otherUser->avatar_url,
                    'email' => $otherUser->email,
                    'phone' => $otherUser->phone,
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
            ->with('sender:id,full_name,username,avatar_url,email,phone')
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        // Check if other participant has read these messages
        $participants = ConversationParticipant::where('conversation_id', $conversationId)
            ->with('user:id,full_name,username,avatar_url,email,phone')
            ->get();

        return response()->json([
            'messages' => $messages->map(fn (Message $message) => $this->messagePayload($message))->values(),
            'participants' => $participants->map(function ($p) {
                return [
                    'user_id' => $p->user_id,
                    'last_read_at' => $p->last_read_at ? $p->last_read_at->toIso8601String() : null,
                    'user' => $p->user ? [
                        'id' => $p->user->id,
                        'full_name' => $p->user->full_name,
                        'username' => $p->user->username,
                        'avatar_url' => $p->user->avatar_url,
                        'email' => $p->user->email,
                        'phone' => $p->user->phone,
                    ] : null,
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
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // tối đa 10MB
            'reply_to_id' => 'nullable|uuid|exists:messages,id',
        ]);

        if (!$request->filled('content') && !$request->hasFile('image')) {
            return response()->json(['message' => 'Nội dung tin nhắn hoặc hình ảnh là bắt buộc.'], 400);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imagePath = $file->store('chats', 'public');
        }

        $message = DB::transaction(function () use ($conversationId, $userId, $request, $imagePath) {
            $now = now();
            $msg = Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'reply_to_id' => $request->input('reply_to_id'),
                'sender_id' => $userId,
                'content' => $request->input('content') ?: '[Hình ảnh]',
                'is_system' => false,
                'reference_type' => $imagePath ? 'image' : null,
                'reference_id' => $imagePath ?: null,
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

        // Broadcast real-time update to all other participants
        $this->broadcastMessage($message, $conversationId, $userId);

        return response()->json($this->messagePayload($message));
    }

    /**
     * React to a message with an emoji
     */
    public function reactToMessage(Request $request, $messageId)
    {
        $request->validate([
            'emoji' => 'required|string|max:50',
        ]);

        $emoji = $request->input('emoji');
        $user = $request->user();

        $message = Message::findOrFail($messageId);

        $isParticipant = ConversationParticipant::where('conversation_id', $message->conversation_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $reactions = $message->reactions ?: [];
        $existingIndex = -1;

        foreach ($reactions as $index => $react) {
            if ($react['user_id'] === $user->id) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== -1) {
            $existingReaction = $reactions[$existingIndex];
            if ($existingReaction['emoji'] === $emoji) {
                array_splice($reactions, $existingIndex, 1);
            } else {
                $reactions[$existingIndex]['emoji'] = $emoji;
            }
        } else {
            $reactions[] = [
                'user_id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'emoji' => $emoji,
            ];
        }

        $message->update(['reactions' => $reactions]);

        broadcast(new \App\Events\MessageReacted($message->conversation_id, $message->id, $reactions))->toOthers();

        return response()->json([
            'message_id' => $message->id,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Toggle message pin state
     */
    public function togglePinMessage(Request $request, $messageId)
    {
        $user = $request->user();
        $message = Message::findOrFail($messageId);

        $isParticipant = ConversationParticipant::where('conversation_id', $message->conversation_id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        $isPinned = !$message->is_pinned;
        $message->update(['is_pinned' => $isPinned]);

        broadcast(new \App\Events\MessagePinned($message->conversation_id, $message->id, $isPinned))->toOthers();

        return response()->json([
            'message_id' => $message->id,
            'is_pinned' => $isPinned,
        ]);
    }

    /**
     * Broadcast the new message to all participants in the conversation
     */
    private function broadcastMessage(Message $message, string $conversationId, int|string $senderId): void
    {
        $messageData = $this->messagePayload($message);

        // Broadcast to the conversation channel (all participants listening)
        broadcast(new MessageSent($conversationId, $messageData))->toOthers();

        // Broadcast conversation update to each participant's personal channel
        $participants = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', '!=', $senderId)
            ->pluck('user_id');

        $conversationData = [
            'id'              => $conversationId,
            'last_message'    => [
                'content'    => $message->content,
                'created_at' => $message->created_at,
                'sender_id'  => $message->sender_id,
            ],
            'last_message_at' => $message->created_at,
        ];

        foreach ($participants as $participantId) {
            broadcast(new ConversationUpdated($participantId, $conversationData));
        }
    }

    public function getEligibleBookings(Request $request, $conversationId)
    {
        $conversation = $this->participantConversation($conversationId, $request->user()->id);
        $clusterIds = $this->conversationManagedClusterIds($conversation, $request->user()->id);

        if (empty($clusterIds)) {
            return response()->json([]);
        }

        $bookings = Booking::query()
            ->with([
                'venueCourt.venueCluster',
                'venueCourt.courtType',
                'venueCluster',
                'payments' => fn ($query) => $query->latest('created_at'),
            ])
            ->where('customer_id', $request->user()->id)
            ->whereIn('venue_cluster_id', $clusterIds)
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->limit(20)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingMessagePayload($booking))
            ->values();

        return response()->json($bookings);
    }

    public function sendBooking(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'booking_id' => 'required|uuid|exists:bookings,id',
        ]);

        $conversation = $this->participantConversation($conversationId, $request->user()->id);
        $clusterIds = $this->conversationManagedClusterIds($conversation, $request->user()->id);

        $booking = Booking::query()
            ->with([
                'venueCourt.venueCluster',
                'venueCourt.courtType',
                'venueCluster',
                'payments' => fn ($query) => $query->latest('created_at'),
            ])
            ->where('id', $validated['booking_id'])
            ->where('customer_id', $request->user()->id)
            ->whereIn('venue_cluster_id', $clusterIds)
            ->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking này không thuộc cụm sân của hội thoại hoặc không phải booking của bạn.',
            ], 403);
        }

        $message = DB::transaction(function () use ($conversationId, $request, $booking) {
            $now = now();
            $msg = Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'sender_id' => $request->user()->id,
                'content' => 'Đã gửi booking #'.$booking->booking_code,
                'is_system' => false,
                'reference_type' => 'booking',
                'reference_id' => $booking->id,
                'created_at' => $now,
            ]);

            Conversation::where('id', $conversationId)->update([
                'last_message_at' => $now,
            ]);

            ConversationParticipant::where('conversation_id', $conversationId)
                ->where('user_id', $request->user()->id)
                ->update([
                    'last_read_at' => $now,
                ]);

            return $msg;
        });

        $this->broadcastMessage($message, $conversationId, $request->user()->id);

        return response()->json($this->messagePayload($message));
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
                'phone' => $u->phone,
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
                $this->syncVenueConversationParticipants($existing, $venue, $userId);

                return response()->json(['id' => $existing->id]);
            }

            $participantIds = $this->venueContactParticipantIds($venue, $userId);

            // Create new venue contact conversation
            $conversation = DB::transaction(function () use ($userId, $venueId, $participantIds) {
                $now = now();
                $conv = Conversation::create([
                    'id' => (string) Str::uuid(),
                    'type' => 'venue_contact',
                    'reference_type' => 'venue_cluster',
                    'reference_id' => $venueId,
                    'created_by' => $userId,
                    'last_message_at' => $now,
                ]);

                foreach ($participantIds as $participantId) {
                    ConversationParticipant::create([
                        'conversation_id' => $conv->id,
                        'user_id' => $participantId,
                        'last_read_at' => $participantId === $userId ? $now : null,
                    ]);
                }

                return $conv;
            });

            return response()->json(['id' => $conversation->id]);
        }

        if ($type === 'saved') {
            // Check if saved conversation (direct type with only 1 participant) already exists
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereDoesntHave('participants', function ($q) use ($userId) {
                    $q->where('user_id', '!=', $userId);
                })
                ->first();

            if ($existing) {
                return response()->json(['id' => $existing->id]);
            }

            // Create new direct conversation with only the current user as participant
            $conversation = DB::transaction(function () use ($userId) {
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

                return $conv;
            });

            return response()->json(['id' => $conversation->id]);
        }

        return response()->json(['message' => 'Loại cuộc trò chuyện không hợp lệ.'], 400);
    }

    private function venueContactParticipantIds(VenueCluster $venue, string $customerId): array
    {
        $staffIds = DB::table('venue_staff_assignments')
            ->where('venue_cluster_id', $venue->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->all();

        return collect([$customerId, $venue->owner_id])
            ->merge($staffIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function syncVenueConversationParticipants(Conversation $conversation, VenueCluster $venue, string $customerId): void
    {
        $participantIds = $this->venueContactParticipantIds($venue, $customerId);
        $existingIds = ConversationParticipant::where('conversation_id', $conversation->id)
            ->pluck('user_id')
            ->all();

        $missingIds = array_values(array_diff($participantIds, $existingIds));
        foreach ($missingIds as $participantId) {
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $participantId,
                'last_read_at' => null,
            ]);
        }
    }

    private function participantConversation(string $conversationId, string $userId): Conversation
    {
        $conversation = Conversation::query()->findOrFail($conversationId);

        $isParticipant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($isParticipant, 403, 'B?n kh?ng thu?c cu?c tr? chuy?n n?y.');

        return $conversation;
    }

    private function conversationManagedClusterIds(Conversation $conversation, string $currentUserId): array
    {
        $clusterIds = collect();

        if ($conversation->type === 'venue_contact' && $conversation->reference_type === 'venue_cluster' && $conversation->reference_id) {
            $clusterIds->push($conversation->reference_id);
        }

        $otherParticipantIds = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $currentUserId)
            ->pluck('user_id');

        if ($otherParticipantIds->isNotEmpty()) {
            $ownerClusterIds = VenueCluster::query()
                ->whereIn('owner_id', $otherParticipantIds)
                ->pluck('id');

            $staffClusterIds = DB::table('venue_staff_assignments')
                ->whereIn('user_id', $otherParticipantIds)
                ->where('status', 'active')
                ->pluck('venue_cluster_id');

            $clusterIds = $clusterIds->merge($ownerClusterIds)->merge($staffClusterIds);
        }

        return $clusterIds->filter()->unique()->values()->all();
    }

    private function messagePayload(Message $message): array
    {
        $message->loadMissing([
            'sender:id,full_name,username,avatar_url,email,phone',
            'replyTo:id,content,sender_id,reference_type,reference_id',
            'replyTo.sender:id,full_name,username',
        ]);
        $payload = $message->toArray();

        if ($message->reference_type === 'booking' && $message->reference_id) {
            $booking = Booking::query()
                ->with(['venueCourt.venueCluster', 'venueCourt.courtType', 'venueCluster', 'payments' => fn ($query) => $query->latest('created_at')])
                ->find($message->reference_id);

            if ($booking) {
                $payload['booking'] = $this->bookingMessagePayload($booking);
            }
        }

        return $payload;
    }

    private function bookingMessagePayload(Booking $booking): array
    {
        $payments = $booking->payments ?? collect();
        $latestPayment = $payments->first();
        $paidAmount = (float) $payments->where('status', 'paid')->sum('amount');
        $isRefunded = $payments->contains(fn ($payment) => $payment->status === 'refunded');
        $bookingDate = $booking->booking_date instanceof \Carbon\Carbon
            ? $booking->booking_date->toDateString()
            : (string) $booking->booking_date;

        return [
            'id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_date' => $bookingDate,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'duration_minutes' => (int) $booking->duration_minutes,
            'total_price' => (float) $booking->total_price,
            'required_payment_amount' => (float) $booking->required_payment_amount,
            'paid_amount' => $paidAmount,
            'payment_option' => $booking->payment_option,
            'payment_status' => $isRefunded
                ? 'refunded'
                : ($latestPayment?->status ?? ((float) $booking->required_payment_amount > 0 ? 'pending' : 'not_required')),
            'status' => $booking->status,
            'status_reason' => $booking->status_reason,
            'venue_cluster' => $booking->venueCluster ?: $booking->venueCourt?->venueCluster,
            'venue_court' => $booking->venueCourt,
        ];
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

        if ($isAUser && $isBUser) {
            return true;
        }

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

    /**
     * Delete a conversation and all its messages
     */
    public function deleteConversation(Request $request, $id)
    {
        $userId = $request->user()->id;

        $participant = ConversationParticipant::where('conversation_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        DB::transaction(function () use ($id) {
            Message::where('conversation_id', $id)->delete();
            ConversationParticipant::where('conversation_id', $id)->delete();
            Conversation::where('id', $id)->delete();
        });

        return response()->json(['success' => true]);
    }

    /**
     * Clear all messages in a conversation
     */
    public function clearMessages(Request $request, $id)
    {
        $userId = $request->user()->id;

        $isParticipant = ConversationParticipant::where('conversation_id', $id)
            ->where('user_id', $userId)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['message' => 'Bạn không thuộc cuộc trò chuyện này.'], 403);
        }

        Message::where('conversation_id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
