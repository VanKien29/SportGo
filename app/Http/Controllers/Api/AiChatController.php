<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Booking;
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
        } else if ($sessionToken) {
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
            'booking_id' => 'nullable|string',
            'venue_cluster_id' => 'nullable|string',
            'session_token' => 'nullable|string',
        ]);

        $prompt = trim($request->input('prompt'));
        $bookingId = $request->input('booking_id');
        $userId = auth('sanctum')->id();
        $sessionToken = $request->header('X-Guest-Token') ?: $request->input('session_token');

        if (! $userId && ! $sessionToken) {
            $sessionToken = (string) Str::uuid();
        }

        // Tìm hoặc tạo cuộc trò chuyện AI trong CSDL
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

        $venues = VenueCluster::whereIn('status', ['active', 'approved'])
            ->limit(20)
            ->get();

        if ($venues->isNotEmpty()) {
            $contextLines[] = "DANH SÁCH CÁC CỤM SÂN THỂ THAO ĐANG HOẠT ĐỘNG TRÊN NỀN TẢNG SPORTGO:";
            foreach ($venues as $v) {
                $courts = VenueCourt::where('venue_cluster_id', $v->id)->get();
                $courtNames = $courts->pluck('name')->filter()->join(', ');

                $contextLines[] = "- Cụm sân: {$v->name}";
                $contextLines[] = "  + Địa chỉ: {$v->address}";
                $contextLines[] = "  + Hotline liên hệ: " . ($v->phone_contact ?? '0902000003');
                $contextLines[] = "  + Danh sách sân chơi: " . ($courtNames ?: 'Sân cầu lông A1, Sân cầu lông A2');
            }
        } else {
            $contextLines[] = "Hệ thống SportGo hiện đang hỗ trợ đặt lịch cho Cụm sân Green Sport Ba Đình (Địa chỉ: Số 12 Kim Mã, Ba Đình - Hotline: 0902000003).";
        }

        if ($bookingId) {
            $code = ltrim($bookingId, '#');
            $booking = Booking::with(['venueCluster', 'venueCourt'])
                ->where('id', $bookingId)
                ->orWhere('booking_code', $code)
                ->first();
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
