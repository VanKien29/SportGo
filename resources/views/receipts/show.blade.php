@php
    $metadata = collect($receipt->metadata ?? [])
        ->reject(fn ($value, $key) => str_starts_with((string) $key, 'mail_') || $key === 'receipt_url')
        ->all();

    $labels = [
        'booking_code' => 'Mã booking',
        'payment_code' => 'Mã thanh toán',
        'request_code' => 'Mã yêu cầu',
        'refund_destination' => 'Nơi nhận hoàn tiền',
        'gateway_refund_txn_id' => 'Mã giao dịch gateway',
        'transfer_reference' => 'Mã đối soát',
        'payment_method' => 'Phương thức',
        'paid_note' => 'Ghi chú chi trả',
        'bank_name' => 'Ngân hàng',
        'bank_code' => 'Mã ngân hàng',
        'account_number' => 'Số tài khoản',
        'account_holder_name' => 'Chủ tài khoản',
    ];

    $formatMeta = function ($value) {
        if (is_array($value)) {
            $label = $value['label'] ?? null;
            if ($label) {
                return $label;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return filled($value) ? $value : '-';
    };

    $recipientName = $receipt->issuedTo?->full_name ?: $receipt->issuedTo?->username ?: '-';
    $recipientContact = $receipt->issuedTo?->email ?: $receipt->issuedTo?->phone ?: '-';
    $invoiceTypeLabel = [
        'refund' => 'Hoàn tiền',
        'withdrawal' => 'Chi trả rút tiền',
        'platform_fee' => 'Phí nền tảng',
        'payment' => 'Thanh toán',
    ][$receipt->receipt_type] ?? $receipt->receipt_type;

    $systemProfile = \App\Models\SystemSetting::profilePayload();
    $systemName = $systemProfile['system_name'] ?: 'SportGo';
    $systemFavicon = $systemProfile['favicon_url'] ?: $systemProfile['logo_url'];
    $systemFaviconUrl = $systemFavicon
        ? (\Illuminate\Support\Str::startsWith($systemFavicon, ['http://', 'https://', '//', 'data:'])
            ? $systemFavicon
            : asset(ltrim($systemFavicon, '/')))
        : null;
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->receipt_code }} - {{ $systemName }}</title>
    @if($systemFaviconUrl)
        <link rel="icon" href="{{ $systemFaviconUrl }}">
        <link rel="shortcut icon" href="{{ $systemFaviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $systemFaviconUrl }}">
    @endif
    <style>
        :root {
            color: #102019;
            font-family: Arial, sans-serif;
            background: #f3f8f5;
        }

        body {
            margin: 0;
            background: #f3f8f5;
        }

        .page {
            max-width: 820px;
            margin: 0 auto;
            padding: 32px 16px;
        }

        .receipt {
            background: #fff;
            border: 1px solid #d7e5dc;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(22, 101, 52, .12);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 26px 30px;
            background: linear-gradient(135deg, #16a34a, #047857);
            color: #fff;
        }

        .brand {
            font-size: 22px;
            font-weight: 900;
        }

        .muted {
            color: #64756c;
        }

        .header .muted {
            color: rgba(255, 255, 255, .82);
        }

        .body {
            padding: 28px 30px 32px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        .code {
            font-weight: 900;
            text-align: right;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 24px 0;
        }

        .parties {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin: 22px 0;
        }

        .party {
            border: 1px solid #dce9e0;
            border-radius: 12px;
            padding: 16px;
            background: #fbfdfb;
        }

        .party h3,
        .section-title {
            margin: 0 0 10px;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: .02em;
            color: #315041;
        }

        .box {
            border: 1px solid #dce9e0;
            border-radius: 12px;
            padding: 14px;
            background: #f8fbf8;
        }

        .label {
            display: block;
            color: #64756c;
            font-size: 13px;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .value {
            font-weight: 900;
            word-break: break-word;
        }

        .amount {
            font-size: 24px;
            color: #047857;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th,
        td {
            padding: 12px 0;
            border-bottom: 1px solid #edf2ef;
            text-align: left;
            vertical-align: top;
        }

        th {
            width: 34%;
            color: #64756c;
            font-size: 13px;
        }

        .footer {
            margin-top: 24px;
            color: #64756c;
            font-size: 13px;
        }

        .total-row td {
            border-bottom: 0;
            font-size: 18px;
            font-weight: 900;
        }

        @media print {
            body {
                background: #fff;
            }

            .page {
                padding: 0;
            }

            .receipt {
                box-shadow: none;
                border-radius: 0;
            }
        }

        @media (max-width: 680px) {
            .header,
            .body {
                padding: 22px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .parties {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="receipt">
            <header class="header">
                <div>
                    <div class="brand">{{ $systemName }}</div>
                    <div class="muted">Hóa đơn nội bộ</div>
                </div>
                <div class="code">
                    {{ $receipt->receipt_code }}
                    <div class="muted">{{ optional($receipt->issued_at)->format('d/m/Y H:i') }}</div>
                </div>
            </header>

            <div class="body">
                <h1>HÓA ĐƠN</h1>
                <div class="muted">{{ $receipt->title }}</div>
                <div class="muted">Trạng thái: {{ $receipt->status }}</div>

                <div class="summary">
                    <div class="box">
                        <span class="label">Mã hóa đơn</span>
                        <span class="value">{{ $receipt->receipt_code }}</span>
                    </div>
                    <div class="box">
                        <span class="label">Ngày phát hành</span>
                        <span class="value">{{ optional($receipt->issued_at)->format('d/m/Y H:i') }}</span>
                    </div>
                    <div class="box">
                        <span class="label">Tổng tiền</span>
                        <span class="value amount">{{ number_format((float) $receipt->amount, 0, ',', '.') }} {{ $receipt->currency }}</span>
                    </div>
                </div>

                <div class="parties">
                    <section class="party">
                        <h3>Bên phát hành</h3>
                        <div><span class="label">Đơn vị</span><span class="value">{{ $systemProfile['company_name'] ?: $systemName }}</span></div>
                        <div><span class="label">Hệ thống</span><span class="value">Nền tảng đặt sân {{ $systemName }}</span></div>
                    </section>
                    <section class="party">
                        <h3>Bên nhận</h3>
                        <div><span class="label">Người nhận</span><span class="value">{{ $recipientName }}</span></div>
                        <div><span class="label">Liên hệ</span><span class="value">{{ $recipientContact }}</span></div>
                    </section>
                </div>

                <h2 class="section-title">Nội dung hóa đơn</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Nội dung</th>
                            <th>Loại</th>
                            <th style="text-align:right">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $receipt->title }}</td>
                            <td>{{ $invoiceTypeLabel }}</td>
                            <td style="text-align:right">{{ number_format((float) $receipt->amount, 0, ',', '.') }} {{ $receipt->currency }}</td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="2" style="text-align:right">Tổng cộng</td>
                            <td style="text-align:right;color:#047857">{{ number_format((float) $receipt->amount, 0, ',', '.') }} {{ $receipt->currency }}</td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="section-title" style="margin-top:24px">Thông tin đối soát</h2>
                <table>
                    <tbody>
                        @forelse ($metadata as $key => $value)
                            <tr>
                                <th>{{ $labels[$key] ?? str_replace('_', ' ', ucfirst((string) $key)) }}</th>
                                <td>{{ $formatMeta($value) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">Không có thông tin bổ sung.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <p class="footer">Hóa đơn được phát hành tự động bởi hệ thống {{ $systemName }} để người dùng, chủ sân và quản trị viên đối soát giao dịch. Tài liệu này không thay thế hóa đơn điện tử có mã cơ quan thuế.</p>
            </div>
        </section>
    </main>
</body>
</html>
