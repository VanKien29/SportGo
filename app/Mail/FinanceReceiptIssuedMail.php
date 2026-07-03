<?php

namespace App\Mail;

use App\Models\InternalReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FinanceReceiptIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly InternalReceipt $receipt,
        public readonly string $receiptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SportGo - Hóa đơn '.$this->receipt->receipt_code);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.finance-receipt-issued',
            with: [
                'receipt' => $this->receipt,
                'receiptUrl' => $this->receiptUrl,
            ],
        );
    }
}
