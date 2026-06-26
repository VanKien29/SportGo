<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>SportGo OTP ký văn bản</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:620px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>SportGo nhận được yêu cầu ký/xác nhận văn bản trên hệ thống.</p>

        <table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border:1px solid #e5e7eb;">
            <tr><td style="padding:10px;color:#64748b;">Tên văn bản</td><td style="padding:10px;font-weight:700;">{{ $signingRequest->document?->title ?? $signingRequest->document_type }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Mã văn bản</td><td style="padding:10px;font-weight:700;">{{ $signingRequest->document_code }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Phiên bản</td><td style="padding:10px;font-weight:700;">v{{ $signingRequest->document_version }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Hành động</td><td style="padding:10px;font-weight:700;">{{ $signingRequest->action }}</td></tr>
            <tr><td style="padding:10px;color:#64748b;">Hash file</td><td style="padding:10px;font-weight:700;">{{ substr($signingRequest->file_hash, 0, 16) }}...</td></tr>
        </table>

        <p>Mã OTP để ký/xác nhận văn bản là:</p>
        <div style="font-size:32px;font-weight:700;letter-spacing:8px;text-align:center;padding:18px 24px;margin:24px 0;background:#ecfdf5;color:#15803d;border-radius:10px;">
            {{ $otp }}
        </div>

        <p>Mã có hiệu lực trong {{ $minutes }} phút. Không cung cấp mã này cho bất kỳ ai. Nếu nội dung văn bản thay đổi, mã OTP này sẽ hết hiệu lực.</p>
        <p>Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
