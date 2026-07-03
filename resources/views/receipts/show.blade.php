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
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt->receipt_code }}</title>
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
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="receipt">
            <header class="header">
                <div>
                    <div class="brand">SportGo</div>
                    <div class="muted">Phiếu tài chính</div>
                </div>
                <div class="code">
                    {{ $receipt->receipt_code }}
                    <div class="muted">{{ optional($receipt->issued_at)->format('d/m/Y H:i') }}</div>
                </div>
            </header>

            <div class="body">
                <h1>{{ $receipt->title }}</h1>
                <div class="muted">Trạng thái: {{ $receipt->status }}</div>

                <div class="summary">
                    <div class="box">
                        <span class="label">Người nhận</span>
                        <span class="value">{{ $receipt->issuedTo?->full_name ?: $receipt->issuedTo?->username ?: '-' }}</span>
                    </div>
                    <div class="box">
                        <span class="label">Loại phiếu</span>
                        <span class="value">{{ $receipt->receipt_type }}</span>
                    </div>
                    <div class="box">
                        <span class="label">Số tiền</span>
                        <span class="value amount">{{ number_format((float) $receipt->amount, 0, ',', '.') }} {{ $receipt->currency }}</span>
                    </div>
                </div>

                <h2>Thông tin chi tiết</h2>
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

                <p class="footer">Phiếu được phát hành tự động bởi hệ thống SportGo. Vui lòng lưu lại mã phiếu để đối soát khi cần.</p>
            </div>
        </section>
    </main>
</body>
</html>
