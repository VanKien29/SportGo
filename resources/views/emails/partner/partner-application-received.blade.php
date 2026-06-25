<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đã nhận đơn đăng ký đối tác SportGo</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $application->applicant_full_name ?? $application->user->full_name }},</p>
        <p>SportGo đã nhận hồ sơ đăng ký đối tác/chủ sân của bạn và sẽ tiến hành kiểm tra thông tin trong thời gian sớm nhất.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border:1px solid #e5e7eb;">
            <tr><td style="padding:10px;color:#64748b;">Người đăng ký</td><td style="padding:10px;font-weight:700;">{{ $application->applicant_full_name ?? '-' }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Tên cụm sân</td><td style="padding:10px;font-weight:700;">{{ $application->venue_name }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Thời gian gửi</td><td style="padding:10px;font-weight:700;">{{ optional($application->submitted_at)->format('H:i d/m/Y') }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Trạng thái</td><td style="padding:10px;font-weight:700;">Đang chờ xét duyệt</td></tr>
        </table>
        <p>Bạn có thể theo dõi tiến độ hồ sơ tại cổng đăng ký đối tác SportGo.</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ url('/partner-application') }}" style="display:inline-block;padding:12px 24px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Xem hồ sơ</a>
        </p>
        <p>Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
