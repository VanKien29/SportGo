<?php

namespace App\Mail\Partner;

use App\Models\DocumentSigningRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerDocumentOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentSigningRequest $signingRequest,
        public string $otp,
        public int $minutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SportGo] Mã OTP ký/xác nhận văn bản');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partner.partner-document-otp',
            with: [
                'signingRequest' => $this->signingRequest,
                'otp' => $this->otp,
                'minutes' => $this->minutes,
            ],
        );
    }
}
