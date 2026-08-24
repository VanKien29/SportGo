<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Booking;
use App\Models\VenueBasePrice;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    protected GeminiService $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Lấy lịch sử chat AI từ CSDL MySQL (Dành cho cả User đăng nhập & Khách vãng lai theo Session Token).
     */
    public function history(Request $request): JsonResponse
    {
        $userId = auth('sanctum')->id();
        $sessionToken = $request->header('X-Guest-Token') ?: $request->input('session_token');

        $query = AiConversation::with('messages');

        if ($userId) {
            $query->where('user_id', $userId);
        } else if ($sessionToken && preg_match('/^[a-zA-Z0-9_-]{8,100}$/', $sessionToken)) {
            $query->where('session_token', $sessionToken);
        } else {
            return response()->json([
                'success' => true,
                'messages' => [],
            ]);
        }

        $conversation = $query->latest('updated_at')->first();

        if (! $conversation) {
            return response()->json([
                'success' => true,
                'messages' => [],
            ]);
        }

        $formatted = $conversation->messages->map(function ($msg) {
            return [
                'id' => 'db_' . $msg->id,
                'sender_id' => $msg->role === 'user' ? 'me' : 'ai_assistant',
                'content' => $msg->content,
                'created_at' => $msg->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->id,
            'messages' => $formatted,
        ]);
    }

    /**
     * Xử lý câu hỏi của khách hàng bằng Gemini AI và lưu trực tiếp vào CSDL MySQL.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'booking_id' => 'nullable|string|max:100',
            'venue_cluster_id' => 'nullable|string|max:100',
            'session_token' => 'nullable|string|max:100',
        ]);

        $prompt = trim(strip_tags($request->input('prompt')));
        $bookingId = $request->input('booking_id');
        $userId = auth('sanctum')->id();
        $sessionToken = $request->header('X-Guest-Token') ?: $request->input('session_token');

        if (! $userId && (! $sessionToken || ! preg_match('/^[a-zA-Z0-9_-]{8,100}$/', $sessionToken))) {
            $sessionToken = (string) Str::uuid();
        }

        // Tìm hoặc tạo cuộc trò chuyện AI trong CSDL với phân quyền chính xác
        $conversation = null;
        if ($userId) {
            $conversation = AiConversation::firstOrCreate(
                ['user_id' => $userId, 'status' => 'active'],
                ['title' => 'Trò chuyện Trợ lý AI SportGo']
            );
        } else {
            $conversation = AiConversation::firstOrCreate(
                ['session_token' => $sessionToken, 'status' => 'active'],
                ['title' => 'Khách vãng lai hỗ trợ AI']
            );
        }

        // 1. Lưu tin nhắn của User vào CSDL
        AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $prompt,
        ]);

        // 2. Thu thập ngữ cảnh dữ liệu thực tế đầy đủ từ hệ thống SportGo
        $contextLines = [];

        $venues = VenueCluster::with(['basePrices'])
            ->whereIn('status', ['active', 'approved'])
            ->limit(20)
            ->get();

        if ($venues->isNotEmpty()) {
            $contextLines[] = "TỔNG SỐ CỤM SÂN ĐANG HOẠT ĐỘNG TRÊN SPORTGO: " . $venues->count() . " CỤM SÂN.";
            
            // Tổng hợp giá thực tế toàn hệ thống để AI trả lời đúng khoảng giá
            $allPrices = VenueBasePrice::whereIn('venue_cluster_id', $venues->pluck('id'))
                ->pluck('price')
                ->map(fn($p) => (float) $p)
                ->filter();

            if ($allPrices->isNotEmpty()) {
                $minPrice = number_format($allPrices->min(), 0, ',', '.');
                $maxPrice = number_format($allPrices->max(), 0, ',', '.');
                $contextLines[] = "THÔNG TIN GIÁ THỰC TẾ HỆ THỐNG SPORTGO:";
                $contextLines[] = "- Giá thuê sân dao động từ {$minPrice} VNĐ/giờ đến {$maxPrice} VNĐ/giờ (theo dữ liệu thực tế trong hệ thống).";
                $contextLines[] = "- Đây là mức giá chính xác, KHÔNG được đưa ra con số ước tính khác ngoài khoảng này.";
                $contextLines[] = "";
            }

            $contextLines[] = "DANH SÁCH CÁC CỤM SÂN THỂ THAO ĐANG HOẠT ĐỘNG TRÊN NỀN TẢNG SPORTGO:";
            foreach ($venues as $v) {
                $courts = VenueCourt::where('venue_cluster_id', $v->id)->get();
                $courtNames = $courts->pluck('name')->filter()->join(', ');

                // Giá thực tế của cụm sân này
                $prices = $v->basePrices->pluck('price')->map(fn($p) => (float) $p)->filter();
                $priceText = 'Chưa cập nhật giá';
                if ($prices->isNotEmpty()) {
                    $minP = number_format($prices->min(), 0, ',', '.');
                    $maxP = number_format($prices->max(), 0, ',', '.');
                    $priceText = $prices->count() > 1
                        ? "từ {$minP} VNĐ đến {$maxP} VNĐ/giờ"
                        : "{$minP} VNĐ/giờ";
                }

                $contextLines[] = "- Cụm sân: {$v->name}";
                $contextLines[] = "  + Địa chỉ: {$v->address}";
                $contextLines[] = "  + Hotline liên hệ: " . ($v->phone_contact ?? 'Chưa cập nhật');
                $contextLines[] = "  + Danh sách sân chơi: " . ($courtNames ?: 'Chưa cập nhật cụ thể');
                $contextLines[] = "  + Giá thuê sân: {$priceText}";
            }
        } else {
            $contextLines[] = "TỔNG SỐ CỤM SÂN ĐANG HOẠT ĐỘNG TRÊN SPORTGO: 0 CỤM SÂN.";
            $contextLines[] = "Hiện tại hệ thống SportGo chưa có cụm sân nào đang hoạt động công khai trên nền tảng. Hãy thông tin chân thực với người dùng rằng hệ thống đang cập nhật danh sách sân mới và chưa có sân để đặt lịch.";
        }

        // Kiểm tra bảo mật quyền sở hữu đơn hàng (Booking Ownership Scope)
        if ($bookingId) {
            $code = ltrim($bookingId, '#');
            $bookingQuery = Booking::with(['venueCluster', 'venueCourt']);

            if ($userId) {
                $bookingQuery->where('customer_id', $userId);
            }

            $booking = $bookingQuery->where(function ($q) use ($bookingId, $code) {
                $q->where('id', $bookingId)->orWhere('booking_code', $code);
            })->first();

            if ($booking) {
                $contextLines[] = "\nThông tin đơn đặt sân hiện tại của khách hàng:";
                $contextLines[] = "- Mã đơn: #{$booking->booking_code}";
                $contextLines[] = "- Cụm sân: " . ($booking->venueCluster->name ?? 'N/A');
                $contextLines[] = "- Sân chơi: " . ($booking->venueCourt->name ?? 'N/A');
                $contextLines[] = "- Ngày chơi: {$booking->booking_date}";
                $contextLines[] = "- Khung giờ: {$booking->start_time} - {$booking->end_time}";
                $contextLines[] = "- Trạng thái đơn: {$booking->status}";
                $contextLines[] = "- Tổng tiền: " . number_format($booking->total_price) . " VNĐ";
            }
        }

        // 3a. Quy định hủy / hoàn tiền từ cấu hình từng cụm sân
        $bookingConfigs = \App\Models\BookingConfig::whereIn('venue_cluster_id', $venues->pluck('id'))->get()->keyBy('venue_cluster_id');
        if ($bookingConfigs->isNotEmpty()) {
            $contextLines[] = "";
            $contextLines[] = "QUY ĐỊNH HỦY SÂN VÀ HOÀN TIỀN TỪNG CỤM SÂN:";
            foreach ($venues as $v) {
                $cfg = $bookingConfigs->get($v->id);
                if ($cfg) {
                    $cancelBefore = $cfg->cancel_before_hours ? "{$cfg->cancel_before_hours} giờ trước khi thi đấu" : 'Không quy định cụ thể';
                    $refundPct    = $cfg->refund_percent !== null ? "{$cfg->refund_percent}% giá trị booking" : 'Theo chính sách SportGo';
                    $openTime     = $cfg->fixed_open_time  ? substr($cfg->fixed_open_time,  0, 5) : '06:00';
                    $closeTime    = $cfg->fixed_close_time ? substr($cfg->fixed_close_time, 0, 5) : '22:00';
                    $contextLines[] = "- {$v->name}: Hủy trước {$cancelBefore} | Hoàn {$refundPct} | Hoạt động: {$openTime} - {$closeTime}";
                }
            }
        }

        // 3b. Lịch sử đặt sân của người dùng hiện tại (nếu đã đăng nhập)
        if ($userId) {
            $recentBookings = \App\Models\Booking::with(['venueCluster', 'venueCourt'])
                ->where('customer_id', $userId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            if ($recentBookings->isNotEmpty()) {
                $contextLines[] = "";
                $contextLines[] = "LỊCH SỬ ĐẶT SÂN GẦN ĐÂY CỦA KHÁCH HÀNG:";
                foreach ($recentBookings as $bk) {
                    $contextLines[] = "- Mã đơn #{$bk->booking_code} | {$bk->venueCluster?->name} | {$bk->booking_date} {$bk->start_time}-{$bk->end_time} | Trạng thái: {$bk->status}";
                }
            }
        }

        $context = implode("\n", $contextLines);

        // 3. Gửi câu hỏi sang Gemini AI
        $reply = $this->geminiService->ask($prompt, $context);

        // 4. Lưu phản hồi của AI vào CSDL
        AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply,
        ]);

        $conversation->touch();

        // 5. Lấy toàn bộ danh sách lịch sử tin nhắn từ CSDL
        $formattedMessages = $conversation->messages()->get()->map(function ($msg) {
            return [
                'id' => 'db_' . $msg->id,
                'sender_id' => $msg->role === 'user' ? 'me' : 'ai_assistant',
                'content' => $msg->content,
                'created_at' => $msg->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'session_token' => $sessionToken,
            'messages' => $formattedMessages,
        ]);
    }
}
