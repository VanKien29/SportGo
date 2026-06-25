<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Chúc mừng bạn đã trở thành đối tác SportGo</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $contract->application->applicant_full_name ?? $contract->application->user->full_name ?? 'Đối tác' }},</p>
        <p>Chúc mừng bạn đã hoàn tất ký giấy/hợp đồng đối tác với SportGo. Tài khoản của bạn đã được cấp quyền <strong>chủ sân</strong>.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border:1px solid #e5e7eb;">
            <tr><td style="padding:10px;color:#64748b;">Tên cụm sân</td><td style="padding:10px;font-weight:700;">{{ $contract->application->venue_name ?? '-' }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Mã hợp đồng</td><td style="padding:10px;font-weight:700;">{{ $contract->contract_code }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Trạng thái</td><td style="padding:10px;font-weight:700;">Đối tác/chủ sân SportGo</td></tr>
        </table>
        <p>Bạn có thể truy cập trang quản lý chủ sân để bắt đầu vận hành cụm sân của mình.</p>
        <p style="text-align:center;margin:24px 0;">
            <a href="{{ url('/owner/dashboard') }}" style="display:inline-block;padding:12px 24px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:bold;">Vào trang quản lý chủ sân</a>
        </p>
        <p>Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
