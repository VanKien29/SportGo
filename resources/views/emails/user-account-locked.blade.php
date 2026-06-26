<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>SportGo - Thông báo khóa tài khoản</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $user->full_name }},</p>

        <p>Tài khoản SportGo của bạn đã bị khóa bởi quản trị viên.</p>

        <div style="margin:24px 0;padding:18px 20px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;">
            <p style="margin:0 0 8px;"><strong>Loại khóa:</strong> {{ $lockTypeLabel }}</p>
            <p style="margin:0;"><strong>Lý do:</strong> {{ $reason }}</p>
            @if ($lockedUntilText)
                <p style="margin:8px 0 0;"><strong>Thời hạn khóa đến:</strong> {{ $lockedUntilText }}</p>
            @endif
        </div>

        <p>Nếu bạn cho rằng quyết định này cần được xem xét lại, vui lòng liên hệ đội ngũ SportGo để được hỗ trợ.</p>

        <p style="margin-top:28px;">Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
