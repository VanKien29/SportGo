<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hồ sơ đăng ký đối tác chưa được duyệt</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $application->applicant_full_name ?? $application->user->full_name }},</p>
        <p>SportGo rất tiếc phải thông báo hồ sơ đăng ký đối tác cho cụm sân <strong>{{ $application->venue_name }}</strong> chưa được duyệt.</p>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:16px;border-radius:8px;margin:16px 0;">
            <strong>Lý do từ chối:</strong><br>
            {{ $application->status_reason }}
        </div>
        <p>Nếu hệ thống cho phép nộp lại, bạn có thể chỉnh sửa thông tin và tạo hồ sơ mới sau khi đã bổ sung đầy đủ tài liệu cần thiết.</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ url('/partner-application') }}" style="display:inline-block;padding:12px 24px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Xem hồ sơ</a>
        </p>
        <p>Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
