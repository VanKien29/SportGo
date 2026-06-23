<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Phản hồi về hồ sơ đăng ký đối tác SportGo</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $application->applicant_full_name ?? $application->user->full_name }},</p>
        <p>Cảm ơn bạn đã quan tâm và gửi đơn đăng ký hợp tác cùng SportGo cho cơ sở <strong>{{ $application->venue_name }}</strong>.</p>
        <p>Sau khi xem xét kỹ lưỡng, chúng tôi rất tiếc phải thông báo hồ sơ của bạn chưa phù hợp với tiêu chí hiện tại của hệ thống. Dưới đây là lý do:</p>
        <div style="background:#fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 8px; margin: 16px 0;">
            {{ $application->status_reason }}
        </div>
        <p>Nếu bạn đã cập nhật lại thông tin hoặc có thắc mắc, vui lòng liên hệ với bộ phận hỗ trợ của chúng tôi hoặc tạo một hồ sơ mới khi đã bổ sung đầy đủ yêu cầu.</p>
        <p>Trân trọng,</p>
        <p>Đội ngũ SportGo</p>
    </div>
</body>
</html>
