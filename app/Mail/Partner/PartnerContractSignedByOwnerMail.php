<?php

namespace App\Mail\Partner;

use App\Models\PartnerContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerContractSignedByOwnerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PartnerContract $contract)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SportGo] Cảm ơn bạn đã ký hợp đồng hợp tác - Chào mừng đối tác mới!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner.partner-contract-signed');
    }
}
