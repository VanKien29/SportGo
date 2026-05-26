<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>SportGo OTP</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:560px;margin:32px auto;background:#ffffff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
        <h1 style="margin:0 0 16px;color:#16a34a;font-size:24px;">SportGo</h1>
        <p>Xin chào {{ $user->full_name }},</p>

        @if ($purpose === 'register')
            <p>Bạn đang đăng ký tài khoản SportGo.</p>
        @else
            <p>SportGo nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p>
        @endif

        <p>Mã xác thực của bạn là:</p>
        <div style="font-size:32px;font-weight:700;letter-spacing:8px;text-align:center;padding:18px 24px;margin:24px 0;background:#ecfdf5;color:#15803d;border-radius:10px;">
            {{ $otp }}
        </div>

        <p>Mã này có hiệu lực trong {{ $minutes }} phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.</p>

        @if ($purpose === 'register')
            <p>Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email.</p>
        @else
            <p>Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này hoặc đổi mật khẩu để bảo vệ tài khoản.</p>
        @endif

        <p style="margin-top:28px;">Trân trọng,<br>Đội ngũ SportGo</p>
    </div>
</body>
</html>
