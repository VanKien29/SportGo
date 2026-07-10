<?php

namespace App\Http\Controllers\Api\Common;

use App\Http\Controllers\Controller;
use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Booking;
use App\Models\BookingSupportRequest;
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
            'content' => 'nullable|string|max:5000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240', // tối đa 10MB
            'reply_to_id' => 'nullable|uuid|exists:messages,id',
        ]);

        if (!$request->filled('content') && !$request->hasFile('image')) {
            return response()->json(['message' => 'Nội dung tin nhắn hoặc hình ảnh là bắt buộc.'], 400);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            
            // Chuyển mã sang webp để triệt tiêu malware ẩn trong metadata của tệp ảnh gốc
            $manager = \Intervention\Image\ImageManager::usingDriver(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->decodePath($file->getPathname());
            
            $filename = 'chat_' . uniqid('', true) . '.webp';
            $imagePath = 'chats/' . $filename;
            
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists('chats')) {
                \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory('chats');
            }
            
            $image->save(storage_path('app/public/' . $imagePath), 80);
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

    public function getRelatedBookings(Request $request, $conversationId)
    {
        $conversation = $this->participantConversation($conversationId, $request->user()->id);
        $clusterIds = $this->conversationOperatorClusterIds($conversation, $request->user()->id);
        $customerIds = $this->conversationCustomerIds($conversation, $clusterIds);

        if (empty($clusterIds) || empty($customerIds)) {
            return response()->json([]);
        }

        $bookings = Booking::query()
            ->with([
                'venueCourt.venueCluster',
                'venueCourt.courtType',
                'venueCluster',
                'payments' => fn ($query) => $query->latest('created_at'),
            ])
            ->whereIn('customer_id', $customerIds)
            ->whereIn('venue_cluster_id', $clusterIds)
            ->orderByRaw("CASE WHEN booking_date >= ? THEN 0 ELSE 1 END", [now()->toDateString()])
            ->orderByRaw("CASE WHEN booking_date >= ? THEN booking_date END ASC", [now()->toDateString()])
            ->orderByRaw("CASE WHEN booking_date >= ? THEN start_time END ASC", [now()->toDateString()])
            ->orderByRaw("CASE WHEN booking_date < ? THEN booking_date END DESC", [now()->toDateString()])
            ->orderByRaw("CASE WHEN booking_date < ? THEN start_time END DESC", [now()->toDateString()])
            ->limit(12)
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
        $operatorClusterIds = $this->conversationOperatorClusterIds($conversation, $request->user()->id);
        $customerIds = $this->conversationCustomerIds($conversation, $operatorClusterIds);

        $bookingQuery = Booking::query()
            ->with([
                'venueCourt.venueCluster',
                'venueCourt.courtType',
                'venueCluster',
                'payments' => fn ($query) => $query->latest('created_at'),
            ])
            ->where('id', $validated['booking_id']);

        if (! empty($operatorClusterIds)) {
            if (empty($customerIds)) {
                return response()->json([
                    'message' => 'Khong tim thay khach hang hop le trong hoi thoai nay.',
                ], 403);
            }

            $bookingQuery
                ->whereIn('venue_cluster_id', $operatorClusterIds)
                ->whereIn('customer_id', $customerIds);
        } else {
            $bookingQuery
                ->where('customer_id', $request->user()->id)
                ->whereIn('venue_cluster_id', $clusterIds);
        }

        $booking = $bookingQuery->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking nay khong thuoc cum san cua hoi thoai hoac ban khong co quyen gui booking nay.',
            ], 403);
        }

        $message = DB::transaction(function () use ($conversationId, $request, $booking) {
            $now = now();
            $msg = Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'sender_id' => $request->user()->id,
                'content' => 'Da gui booking #'.$booking->booking_code,
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
    public function createBookingSupportRequest(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'booking_id' => 'required|uuid|exists:bookings,id',
            'request_type' => 'required|string|in:reschedule,change_court,cancel_booking,payment,late_arrival,refund,other',
            'note' => 'nullable|string|max:1000',
        ]);

        $conversation = $this->participantConversation($conversationId, $request->user()->id);
        $clusterIds = $this->conversationManagedClusterIds($conversation, $request->user()->id);

        $booking = Booking::query()
            ->with(['venueCourt.venueCluster', 'venueCourt.courtType', 'venueCluster', 'payments' => fn ($query) => $query->latest('created_at')])
            ->where('id', $validated['booking_id'])
            ->where('customer_id', $request->user()->id)
            ->whereIn('venue_cluster_id', $clusterIds)
            ->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking nay khong thuoc hoi thoai hoac khong phai booking cua ban.',
            ], 403);
        }

        $message = DB::transaction(function () use ($conversationId, $request, $booking, $validated) {
            $now = now();
            $supportRequest = BookingSupportRequest::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'booking_id' => $booking->id,
                'customer_id' => $request->user()->id,
                'venue_cluster_id' => $booking->venue_cluster_id,
                'request_type' => $validated['request_type'],
                'note' => $validated['note'] ?? null,
                'status' => 'pending',
            ]);

            $msg = Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'sender_id' => $request->user()->id,
                'content' => 'Yeu cau ho tro booking #'.$booking->booking_code,
                'is_system' => false,
                'reference_type' => 'booking_support_request',
                'reference_id' => $supportRequest->id,
                'created_at' => $now,
            ]);

            Conversation::where('id', $conversationId)->update(['last_message_at' => $now]);
            ConversationParticipant::where('conversation_id', $conversationId)
                ->where('user_id', $request->user()->id)
                ->update(['last_read_at' => $now]);

            return $msg;
        });

        $this->broadcastMessage($message, $conversationId, $request->user()->id);

        return response()->json($this->messagePayload($message));
    }

    public function updateBookingSupportRequest(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:acknowledged,resolved,rejected',
            'resolution_note' => 'nullable|string|max:1000',
        ]);

        $supportRequest = BookingSupportRequest::query()
            ->with(['booking.venueCourt.venueCluster', 'booking.venueCourt.courtType', 'booking.venueCluster', 'booking.payments' => fn ($query) => $query->latest('created_at')])
            ->findOrFail($id);

        $conversation = $this->participantConversation($supportRequest->conversation_id, $request->user()->id);
        $operatorClusterIds = $this->conversationOperatorClusterIds($conversation, $request->user()->id);

        if (! in_array($supportRequest->venue_cluster_id, $operatorClusterIds, true)) {
            return response()->json([
                'message' => 'Ban khong co quyen xu ly yeu cau nay.',
            ], 403);
        }

        $message = DB::transaction(function () use ($supportRequest, $request, $validated) {
            $now = now();
            $supportRequest->update([
                'status' => $validated['status'],
                'handled_by' => $request->user()->id,
                'handled_at' => $now,
                'resolution_note' => $validated['resolution_note'] ?? $supportRequest->resolution_note,
            ]);

            $msg = Message::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $supportRequest->conversation_id,
                'sender_id' => $request->user()->id,
                'content' => 'Cap nhat yeu cau booking #'.$supportRequest->booking?->booking_code,
                'is_system' => false,
                'reference_type' => 'booking_support_request',
                'reference_id' => $supportRequest->id,
                'created_at' => $now,
            ]);

            Conversation::where('id', $supportRequest->conversation_id)->update(['last_message_at' => $now]);
            ConversationParticipant::where('conversation_id', $supportRequest->conversation_id)
                ->where('user_id', $request->user()->id)
                ->update(['last_read_at' => $now]);

            return $msg;
        });

        $this->broadcastMessage($message, $supportRequest->conversation_id, $request->user()->id);

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

    private function conversationOperatorClusterIds(Conversation $conversation, string $currentUserId): array
    {
        $managedClusterIds = collect($this->managedClusterIdsForUser($currentUserId));

        if ($managedClusterIds->isEmpty()) {
            return [];
        }

        if ($conversation->type === 'venue_contact' && $conversation->reference_type === 'venue_cluster' && $conversation->reference_id) {
            return $managedClusterIds
                ->intersect([$conversation->reference_id])
                ->values()
                ->all();
        }

        return $managedClusterIds->values()->all();
    }

    private function managedClusterIdsForUser(string $userId): array
    {
        $ownerClusterIds = VenueCluster::query()
            ->where('owner_id', $userId)
            ->pluck('id');

        $staffClusterIds = DB::table('venue_staff_assignments')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('venue_cluster_id');

        return $ownerClusterIds
            ->merge($staffClusterIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function conversationCustomerIds(Conversation $conversation, array $clusterIds): array
    {
        if (empty($clusterIds)) {
            return [];
        }

        $participantIds = ConversationParticipant::where('conversation_id', $conversation->id)
            ->pluck('user_id')
            ->all();

        if (empty($participantIds)) {
            return [];
        }

        $operatorIds = VenueCluster::query()
            ->whereIn('id', $clusterIds)
            ->pluck('owner_id')
            ->filter()
            ->merge(
                DB::table('venue_staff_assignments')
                    ->whereIn('venue_cluster_id', $clusterIds)
                    ->where('status', 'active')
                    ->pluck('user_id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($conversation->created_by && ! in_array($conversation->created_by, $operatorIds, true)) {
            return in_array($conversation->created_by, $participantIds, true)
                ? [$conversation->created_by]
                : [];
        }

        return collect($participantIds)
            ->diff($operatorIds)
            ->values()
            ->all();
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

        if ($message->reference_type === 'booking_support_request' && $message->reference_id) {
            $supportRequest = BookingSupportRequest::query()
                ->with([
                    'booking.venueCourt.venueCluster',
                    'booking.venueCourt.courtType',
                    'booking.venueCluster',
                    'booking.payments' => fn ($query) => $query->latest('created_at'),
                    'customer:id,full_name,username,phone,email',
                    'handledBy:id,full_name,username',
                ])
                ->find($message->reference_id);

            if ($supportRequest) {
                $payload['support_request'] = $this->bookingSupportRequestPayload($supportRequest);
            }
        }

        return $payload;
    }

    private function bookingSupportRequestPayload(BookingSupportRequest $supportRequest): array
    {
        $supportRequest->loadMissing([
            'booking.venueCourt.venueCluster',
            'booking.venueCourt.courtType',
            'booking.venueCluster',
            'booking.payments' => fn ($query) => $query->latest('created_at'),
            'customer:id,full_name,username,phone,email',
            'handledBy:id,full_name,username',
        ]);

        return [
            'id' => $supportRequest->id,
            'conversation_id' => $supportRequest->conversation_id,
            'booking_id' => $supportRequest->booking_id,
            'customer_id' => $supportRequest->customer_id,
            'venue_cluster_id' => $supportRequest->venue_cluster_id,
            'request_type' => $supportRequest->request_type,
            'note' => $supportRequest->note,
            'status' => $supportRequest->status,
            'handled_by' => $supportRequest->handled_by,
            'handled_at' => $supportRequest->handled_at ? $supportRequest->handled_at->toIso8601String() : null,
            'resolution_note' => $supportRequest->resolution_note,
            'created_at' => $supportRequest->created_at ? $supportRequest->created_at->toIso8601String() : null,
            'updated_at' => $supportRequest->updated_at ? $supportRequest->updated_at->toIso8601String() : null,
            'booking' => $supportRequest->booking ? $this->bookingMessagePayload($supportRequest->booking) : null,
            'customer' => $supportRequest->customer ? [
                'id' => $supportRequest->customer->id,
                'full_name' => $supportRequest->customer->full_name,
                'username' => $supportRequest->customer->username,
                'phone' => $supportRequest->customer->phone,
                'email' => $supportRequest->customer->email,
            ] : null,
            'handled_by_user' => $supportRequest->handledBy ? [
                'id' => $supportRequest->handledBy->id,
                'full_name' => $supportRequest->handledBy->full_name,
                'username' => $supportRequest->handledBy->username,
            ] : null,
        ];
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

        DB::transaction(function () use ($id, $userId) {
            Message::where('conversation_id', $id)->delete();
            ConversationParticipant::where('conversation_id', $id)->delete();
            Conversation::where('id', $id)->delete();

            \App\Models\AuditLog::create([
                'actor_id' => $userId,
                'actor_type' => 'user',
                'action' => 'delete',
                'module' => 'chat',
                'entity_type' => 'conversation',
                'entity_id' => $id,
                'reason' => 'User deleted conversation',
            ]);
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

        DB::transaction(function () use ($id, $userId) {
            Message::where('conversation_id', $id)->delete();

            \App\Models\AuditLog::create([
                'actor_id' => $userId,
                'actor_type' => 'user',
                'action' => 'clear_messages',
                'module' => 'chat',
                'entity_type' => 'conversation',
                'entity_id' => $id,
                'reason' => 'User cleared message history',
            ]);
        });

        return response()->json(['success' => true]);
    }
}
