<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hồ sơ đăng ký đối tác cần bổ sung</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#d97706;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $application->applicant_full_name ?? $application->user->full_name }},</p>
        <p>Hồ sơ đăng ký đối tác/chủ sân của bạn cần bổ sung thêm thông tin hoặc giấy tờ trước khi SportGo tiếp tục thẩm định.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border:1px solid #e5e7eb;">
            <tr><td style="padding:10px;color:#64748b;">Tên cụm sân</td><td style="padding:10px;font-weight:700;">{{ $application->venue_name }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Trạng thái</td><td style="padding:10px;font-weight:700;">Cần bổ sung hồ sơ</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Nội dung cần bổ sung</td><td style="padding:10px;font-weight:700;">{{ $application->status_reason ?? '-' }}</td></tr>
        </table>
        <p>Vui lòng đăng nhập hệ thống SportGo để kiểm tra hồ sơ và chuẩn bị lại tài liệu theo yêu cầu.</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ url('/partner-application') }}" style="display:inline-block;padding:12px 24px;background:#d97706;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Xem hồ sơ</a>
        </p>
        <p>Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
