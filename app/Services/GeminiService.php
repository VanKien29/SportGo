<?php

namespace App\Services;

use App\Models\VenueCluster;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent';

    public function __construct()
    {
        $this->apiKey = (string) env('GEMINI_API_KEY');
    }

    /**
     * Gửi câu hỏi và ngữ cảnh hệ thống tới Gemini AI API (Không dùng bất kỳ Fallback dự phòng nào).
     */
    public function ask(string $userPrompt, string $systemContext = ''): string
    {
        $userPrompt = $this->normalizeVietnamese($userPrompt);

        $systemInstruction = "Bạn là Trợ lý AI thông minh của nền tảng đặt sân thể thao SportGo (SportGo AI Assistant).\n"
            . "Nhiệm vụ của bạn là hỗ trợ người chơi tìm kiếm sân đấu, tư vấn khung giờ chơi, hướng dẫn quy định hoàn hủy và giải đáp thắc mắc dịch vụ.\n"
            . "QUY TẮC BẮT BUỘC:\n"
            . "1. Trả lời bằng tiếng Việt lịch sự, tự nhiên, linh hoạt, chính xác hệt như ChatGPT/Gemini.\n"
            . "2. TUYỆT ĐỐI KHÔNG SỬ DỤNG BẤT KỲ EMOJI NÀO TRONG CÂU TRẢ LỜI.\n"
            . "3. Nếu có dữ liệu ngữ cảnh sân đấu bên dưới, hãy kết hợp thông tin chính xác từ hệ thống SportGo để tư vấn.";

        $fullPrompt = $systemInstruction . "\n\n--- DỮ LIỆU NGỮ CẢNH SPORTGO ---\n" . $systemContext . "\n\n--- CÂU HỎI CỦA KHÁCH HÀNG ---\n" . $userPrompt;

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $fullPrompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 800,
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                $textParts = [];
                foreach ($parts as $part) {
                    if (!empty($part['text'])) {
                        $textParts[] = $part['text'];
                    }
                }
                $reply = implode("\n", $textParts);
                if (!empty($reply)) {
                    return $this->stripEmojis($reply);
                }
            }

            Log::warning('Gemini API Response Error', ['status' => $response->status(), 'body' => $response->body()]);
            return "Máy chủ AI Google Gemini phản hồi mã " . $response->status() . ": " . $response->body();

        } catch (\Throwable $e) {
            Log::error('Gemini API Exception', ['error' => $e->getMessage()]);
            return "Ngoại lệ kết nối AI: " . $e->getMessage();
        }
    }

    /**
     * Sinh câu trả lời dự phòng thông minh linh hoạt theo ý định người dùng khi Google Gemini API vượt hạn ngạch.
     */
    private function generateLocalFallbackResponse(string $prompt, string $systemContext): string
    {
        $normPrompt = $this->normalizeVietnamese($prompt);
        $asciiPrompt = $this->removeAccents($normPrompt);

        // 1. Ý định Chào hỏi (Greeting)
        if (
            $asciiPrompt === 'xin chao' ||
            $asciiPrompt === 'chao' ||
            $asciiPrompt === 'hi' ||
            $asciiPrompt === 'hello' ||
            str_contains($asciiPrompt, 'xin chao') ||
            str_starts_with($asciiPrompt, 'chao') ||
            str_contains($asciiPrompt, 'la ai')
        ) {
            return "Xin chào! Tôi là Trợ lý AI của SportGo. Tôi có thể hỗ trợ bạn tìm kiếm cụm sân thể thao, xem lịch trống, tư vấn khung giờ chơi hoặc giải đáp các quy định dịch vụ. Bạn cần hỗ trợ gì hôm nay?";
        }

        // 2. Ý định Hoàn tiền / Hủy sân / Đổi giờ
        if (
            str_contains($asciiPrompt, 'hoan tien') ||
            str_contains($asciiPrompt, 'coc') ||
            str_contains($asciiPrompt, 'huy')
        ) {
            return "Về chính sách hoàn tiền cọc:\n"
                . "1. Bạn có thể hủy đơn và nhận lại tiền cọc về Ví SportGo dựa trên quy định thời gian hủy trước giờ chơi của từng cụm sân.\n"
                . "2. Khi hủy thành công trước giờ quy định, tiền sẽ được cập nhật tự động vào số dư ví của bạn.";
        }

        if (
            str_contains($asciiPrompt, 'doi gio') ||
            str_contains($asciiPrompt, 'lui') ||
            str_contains($asciiPrompt, 'muon') ||
            str_contains($asciiPrompt, 'khung gio')
        ) {
            return "Để xin lùi hoặc đổi khung giờ chơi:\n"
                . "Bạn hãy truy cập vào Đơn đặt sân chi tiết, sử dụng nút 'Chat hỗ trợ với sân' để nhắn tin trực tiếp cho Chủ sân hoặc Nhân viên trực ca duyệt hỗ trợ nhanh nhất.";
        }

        // 3. Ý định Tìm kiếm cụm sân / Địa điểm / Môn thể thao
        if (
            str_contains($asciiPrompt, 'san') ||
            str_contains($asciiPrompt, 'tim') ||
            str_contains($asciiPrompt, 'ha noi') ||
            str_contains($asciiPrompt, 'ba dinh') ||
            str_contains($asciiPrompt, 'cau giay') ||
            str_contains($asciiPrompt, 'ha dong') ||
            str_contains($asciiPrompt, 'choi') ||
            str_contains($asciiPrompt, 'the thao')
        ) {
            $query = VenueCluster::whereIn('status', ['active', 'approved']);

            if (str_contains($asciiPrompt, 'ba dinh')) {
                $query->where(function ($q) {
                    $q->where('address', 'like', '%Ba Đình%')->orWhere('name', 'like', '%Ba Đình%');
                });
            } else if (str_contains($asciiPrompt, 'cau giay')) {
                $query->where(function ($q) {
                    $q->where('address', 'like', '%Cầu Giấy%')->orWhere('name', 'like', '%Cầu Giấy%');
                });
            } else if (str_contains($asciiPrompt, 'ha dong')) {
                $query->where(function ($q) {
                    $q->where('address', 'like', '%Hà Đông%')->orWhere('name', 'like', '%Hà Đông%');
                });
            }

            $venues = $query->limit(5)->get();
            if ($venues->isEmpty()) {
                $venues = VenueCluster::whereIn('status', ['active', 'approved'])->limit(5)->get();
            }

            if ($venues->isNotEmpty()) {
                $reply = "Dựa trên dữ liệu thực tế từ hệ thống SportGo, tôi tìm thấy các cụm sân thể thao đáp ứng yêu cầu của bạn:\n\n";
                foreach ($venues as $v) {
                    $reply .= "- Cụm sân " . $v->name . " (Địa chỉ: " . $v->address . " - Hotline: " . ($v->phone_contact ?? '0902000003') . ")\n";
                }
                $reply .= "\nBạn có thể vào mục Tìm Sân trên thanh menu để xem lịch và đặt slot trực tiếp.";
                return $reply;
            }
        }

        // 4. Các câu hỏi linh hoạt khác
        return "Tôi là Trợ lý AI của SportGo. Bạn có thể cho tôi biết cụ thể hơn nhu cầu của bạn (ví dụ: tìm sân tại khu vực nào, loại hình thể thao nào, hoặc quy định hủy đổi sân) để tôi hỗ trợ bạn tốt nhất nhé!";
    }

    private function normalizeVietnamese(string $str): string
    {
        if (class_exists('\Normalizer')) {
            $str = \Normalizer::normalize($str, \Normalizer::FORM_C) ?? $str;
        }
        return mb_strtolower(trim($str), 'UTF-8');
    }

    private function removeAccents(string $str): string
    {
        $str = $this->normalizeVietnamese($str);
        $str = preg_replace('/[àáạảãâầấậẩẫăằắặẳẵ]/u', 'a', $str);
        $str = preg_replace('/[èéẹẻẽêềếệểễ]/u', 'e', $str);
        $str = preg_replace('/[ìíịỉĩ]/u', 'i', $str);
        $str = preg_replace('/[òóọỏõôồốộổỗơờớợởỡ]/u', 'o', $str);
        $str = preg_replace('/[ùúụủũưừứựửữ]/u', 'u', $str);
        $str = preg_replace('/[ỳýỵỷỹ]/u', 'y', $str);
        $str = preg_replace('/[đ]/u', 'd', $str);
        return $str;
    }

    /**
     * Loại bỏ các ký tự Emoji ra khỏi chuỗi văn bản.
     */
    private function stripEmojis(string $string): string
    {
        $pattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        return preg_replace($pattern, '', $string);
    }
}
