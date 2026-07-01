<?php

namespace App\Mail\Partner;

use App\Models\PartnerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PartnerApplication $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SportGo] Hồ sơ đăng ký đối tác chưa được duyệt');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner.partner-application-rejected');
    }
}
