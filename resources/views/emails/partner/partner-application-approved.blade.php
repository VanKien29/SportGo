<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hồ sơ đăng ký đối tác SportGo đã được phê duyệt</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $application->applicant_full_name ?? $application->user->full_name }},</p>
        <p>Chúc mừng bạn! Hồ sơ đăng ký đối tác cho cơ sở <strong>{{ $application->venue_name }}</strong> của bạn đã được SportGo xác nhận và phê duyệt.</p>
        <p>Để hoàn tất thủ tục hợp tác, bạn vui lòng truy cập vào Cổng đăng ký đối tác để xem trước tài liệu và thực hiện ký số xác nhận Mẫu 01.</p>
        <div style="text-align:center; margin: 24px 0;">
            <a href="{{ url('/partner-application') }}" style="display:inline-block;padding:12px 24px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Truy cập để Ký hợp đồng</a>
        </div>
        <p>Trân trọng,</p>
        <p>Đội ngũ SportGo</p>
    </div>
</body>
</html>
