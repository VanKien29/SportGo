<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->title }}</title>
</head>
<body style="margin:0;background:#f4f7f5;color:#17231c;font-family:Arial,sans-serif">
    <div style="max-width:640px;margin:0 auto;padding:28px 16px">
        <div style="border:1px solid #d7e5dc;background:#ffffff;border-radius:12px;overflow:hidden">
            <div style="padding:18px 22px;background:#16a34a;color:#ffffff">
                <div style="font-size:20px;font-weight:800">SportGo</div>
                <div style="font-size:13px;opacity:.9;margin-top:4px">Hóa đơn đã được phát hành</div>
            </div>
            <div style="padding:24px 22px;line-height:1.6">
                <p style="margin:0 0 14px">Xin chào {{ $receipt->issuedTo?->full_name ?: $receipt->issuedTo?->username ?: 'bạn' }},</p>
                <p style="margin:0 0 18px">SportGo đã phát hành hóa đơn cho giao dịch sau:</p>

                <table style="width:100%;border-collapse:collapse;margin:0 0 20px">
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #edf2ef;color:#617067">Mã hóa đơn</td>
                        <td style="padding:10px 0;border-bottom:1px solid #edf2ef;text-align:right;font-weight:800">{{ $receipt->receipt_code }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #edf2ef;color:#617067">Nội dung</td>
                        <td style="padding:10px 0;border-bottom:1px solid #edf2ef;text-align:right;font-weight:800">{{ $receipt->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid #edf2ef;color:#617067">Số tiền</td>
                        <td style="padding:10px 0;border-bottom:1px solid #edf2ef;text-align:right;font-weight:800">{{ number_format((float) $receipt->amount, 0, ',', '.') }} {{ $receipt->currency }}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;color:#617067">Ngày phát hành</td>
                        <td style="padding:10px 0;text-align:right;font-weight:800">{{ optional($receipt->issued_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                </table>

                <a href="{{ $receiptUrl }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:800">Xem hóa đơn</a>
                <p style="margin:18px 0 0;color:#617067;font-size:13px">Liên kết xem hóa đơn có hiệu lực trong 30 ngày.</p>
            </div>
        </div>
    </div>
</body>
</html>
