<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject ?? 'Nhắc phí duy trì' }}</title>
</head>
<body style="margin:0;background:#f4f7f5;color:#1f2937;font-family:Arial,sans-serif">
    <div style="max-width:620px;margin:0 auto;padding:28px 16px">
        <div style="border:1px solid #dbe5df;background:#ffffff">
            <div style="padding:18px 22px;background:#15803d;color:#ffffff;font-size:20px;font-weight:700">
                SportGo
            </div>
            <div style="padding:24px 22px;white-space:pre-line;line-height:1.65">{{ $mailContent }}</div>
        </div>
    </div>
</body>
</html>
