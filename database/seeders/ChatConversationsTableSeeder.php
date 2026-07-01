<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\VenueCluster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ChatConversationsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('conversations') || ! Schema::hasTable('conversation_participants') || ! Schema::hasTable('messages')) {
            return;
        }

        // Clean existing chat data first to ensure clean run
        Schema::disableForeignKeyConstraints();
        Message::truncate();
        ConversationParticipant::truncate();
        Conversation::truncate();
        Schema::enableForeignKeyConstraints();

        // Get key users
        $superadmin = User::where('username', 'superadmin')->first();
        $admin = User::where('username', 'admin')->first() ?: $superadmin;
        $systemstaff = User::where('username', 'systemstaff')->first();
        $moderator = User::where('username', 'moderator')->first();
        
        $owner = User::where('username', 'owner')->first();
        $venuestaff = User::where('username', 'venuestaff')->first();
        
        $user = User::where('username', 'user')->first();
        $user1 = User::where('username', 'user1')->first();
        $user2 = User::where('username', 'user2')->first();

        // Get clusters
        $clusters = VenueCluster::all();

        if (!$admin || !$user || !$owner) {
            return;
        }

        // Helper function to create conversation and messages
        $createChat = function ($type, $title, array $participants, array $messages, $refType = null, $refId = null) {
            $conversation = Conversation::create([
                'type' => $type,
                'title' => $title,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'created_by' => $participants[0]->id,
                'last_message_at' => now(),
            ]);

            foreach ($participants as $p) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $p->id,
                    'last_read_at' => now()->subMinutes(rand(1, 10)),
                    'joined_at' => now()->subDays(5),
                ]);
            }

            $lastMsgAt = now()->subDays(2);
            foreach ($messages as $msgData) {
                $lastMsgAt = $lastMsgAt->addHours(rand(1, 4));
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $msgData['sender_id'],
                    'content' => $msgData['content'],
                    'is_system' => $msgData['is_system'] ?? false,
                    'created_at' => $lastMsgAt,
                ]);
            }

            $conversation->update(['last_message_at' => $lastMsgAt]);
        };

        // Scenario 1: Khách hàng (user) <-> Chủ sân (owner)
        $createChat(
            'direct',
            $user->full_name,
            [$user, $owner],
            [
                ['sender_id' => $user->id, 'content' => 'Chào anh/chị, cho em hỏi sân SportGo Cầu Giấy tối nay còn slot 18h-20h không ạ?'],
                ['sender_id' => $owner->id, 'content' => 'Chào em, tối nay slot 18h-20h sân số 1 và 2 đều đã có người đặt rồi. Sân số 3 còn trống từ 19h-21h em có muốn đổi giờ không?'],
                ['sender_id' => $user->id, 'content' => 'Dạ thế em đặt sân số 3 từ 19h nhé ạ.'],
                ['sender_id' => $owner->id, 'content' => 'Ok em, em book trực tiếp trên app giúp anh/chị nha.'],
            ]
        );

        if ($user1) {
            $createChat(
                'direct',
                $user1->full_name,
                [$user1, $owner],
                [
                    ['sender_id' => $user1->id, 'content' => 'Anh ơi bên mình có chỗ gửi xe máy qua đêm không ạ?'],
                    ['sender_id' => $owner->id, 'content' => 'Chào bạn, sân bên mình đóng cửa lúc 22h30 nên không nhận gửi xe qua đêm bạn nhé.'],
                ]
            );
        }

        // Scenario 2: Chủ sân (owner) <-> Nhân viên sân (venuestaff)
        if ($venuestaff) {
            $createChat(
                'direct',
                $venuestaff->full_name,
                [$owner, $venuestaff],
                [
                    ['sender_id' => $owner->id, 'content' => 'Hôm nay ca tối em kiểm tra kỹ lưới ở sân số 2 giúp anh nhé, khách vừa báo hơi bị trùng.'],
                    ['sender_id' => $venuestaff->id, 'content' => 'Dạ vâng anh, em vừa căng lại lưới và gia cố lại cọc rồi ạ. Sân số 2 đã sẵn sàng đón khách ca sau.'],
                    ['sender_id' => $owner->id, 'content' => 'Tốt lắm, cảm ơn em.'],
                ]
            );
        }

        // Scenario 3: Admin (admin) <-> Kiểm duyệt viên (moderator) - Nhân sự nội bộ
        if ($moderator) {
            $createChat(
                'direct',
                $moderator->full_name,
                [$admin, $moderator],
                [
                    ['sender_id' => $moderator->id, 'content' => 'Báo cáo Admin, tôi vừa phát hiện một số bài đăng spam quảng cáo cá độ trên bảng tin tìm đồng đội.'],
                    ['sender_id' => $admin->id, 'content' => 'Chào bạn, bạn cứ tiến hành ẩn bài viết đó và khóa tài khoản vi phạm theo đúng quy trình nhé.'],
                    ['sender_id' => $moderator->id, 'content' => 'Rõ, tôi đã xử lý xong và ghi nhật ký kiểm duyệt rồi ạ.'],
                ]
            );
        }

        // Scenario 4: Admin (admin) <-> Nhân viên hệ thống (systemstaff) - Nhân sự nội bộ
        if ($systemstaff) {
            $createChat(
                'direct',
                $systemstaff->full_name,
                [$admin, $systemstaff],
                [
                    ['sender_id' => $systemstaff->id, 'content' => 'Bảng cấu hình chính sách hoàn tiền mới đã được soạn thảo xong, nhờ Admin phê duyệt.'],
                    ['sender_id' => $admin->id, 'content' => 'Ok, tôi đã xem qua bản nháp. Để tôi kiểm tra lại phần tỷ lệ khấu trừ rồi sẽ duyệt trong sáng nay.'],
                ]
            );
        }

        // Scenario 5: Venue contact (Hỏi chủ cụm sân) - Khách hàng (user) <-> Chủ sân (owner)
        if ($clusters->count() > 0) {
            $firstCluster = $clusters->first();
            $createChat(
                'venue_contact',
                $firstCluster->name,
                [$user, $owner],
                [
                    ['sender_id' => $user->id, 'content' => 'Cụm sân này có chỗ đỗ ô tô rộng không ạ?'],
                    ['sender_id' => $owner->id, 'content' => 'Chào bạn, cụm sân bên mình có bãi đỗ xe ô tô rộng rãi, hoàn toàn miễn phí cho khách chơi sân nhé.'],
                ],
                'venue_cluster',
                $firstCluster->id
            );
        }
    }
}
