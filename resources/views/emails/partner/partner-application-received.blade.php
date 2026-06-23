<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Xác nhận nhận đơn đăng ký đối tác SportGo</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $application->applicant_full_name ?? $application->user->full_name }},</p>
        <p>Cảm ơn bạn đã quan tâm và gửi đơn đăng ký trở thành đối tác/chủ sân trên nền tảng SportGo.</p>
        <p>Hệ thống đã ghi nhận hồ sơ đăng ký cho cơ sở: <strong>{{ $application->venue_name }}</strong>.</p>
        <p>Hồ sơ của bạn hiện đang ở trạng thái <strong>Chờ phê duyệt</strong>. Đội ngũ SportGo sẽ tiến hành xem xét và liên hệ lại với bạn trong thời gian sớm nhất (thông thường từ 1-3 ngày làm việc).</p>
        <p>Bạn có thể theo dõi tiến độ hồ sơ bằng cách truy cập vào Cổng đăng ký đối tác SportGo:</p>
        <div style="text-align:center; margin: 24px 0;">
            <a href="{{ url('/partner-application') }}" style="display:inline-block;padding:12px 24px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Kiểm tra hồ sơ</a>
        </div>
        <p>Trân trọng,</p>
        <p>Đội ngũ SportGo</p>
    </div>
</body>
</html>
