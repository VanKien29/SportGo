<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Chúc mừng ký kết thành công</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #22c55e;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 0 0 8px 8px;
        }
        .btn {
            display: inline-block;
            background-color: #0f172a;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.8em;
            color: #6b7280;
            text-align: center;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-table td:first-child {
            font-weight: bold;
            color: #4b5563;
            width: 40%;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Chúc mừng ký kết thành công</h1>
    </div>
    
    <div class="content">
        <p>Xin chào <strong>{{ $contract->owner->full_name ?? 'Đối tác' }}</strong>,</p>
        
        <p>Cảm ơn bạn đã hoàn tất ký hợp đồng hợp tác với SportGo. Tài khoản của bạn đã được cấp quyền Chủ sân và bạn có thể bắt đầu quản lý cụm sân ngay bây giờ.</p>
        
        <table class="info-table">
            <tbody>
                <tr>
                    <td>Mã hợp đồng:</td>
                    <td>{{ $contract->contract_code }}</td>
                </tr>
                <tr>
                    <td>Tên cụm sân:</td>
                    <td>{{ $contract->application->venue_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Thời gian ký:</td>
                    <td>{{ \Carbon\Carbon::parse($contract->owner_signed_at)->format('H:i d/m/Y') }}</td>
                </tr>
            </tbody>
        </table>

        <p>Hợp đồng sẽ được SportGo ký xác nhận trong thời gian sớm nhất để hoàn thiện bản hợp đồng chính thức. Cảm ơn bạn đã đồng hành cùng chúng tôi.</p>
        
        <div style="text-align: center;">
            <a href="{{ url('/owner/dashboard') }}" class="btn">Vào trang quản lý sân</a>
        </div>
    </div>
    
    <div class="footer">
        <p>Đây là email tự động từ hệ thống SportGo. Vui lòng không trả lời email này.</p>
        <p>&copy; {{ date('Y') }} SportGo. All rights reserved.</p>
    </div>
</body>
</html>
