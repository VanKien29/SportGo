<?php

namespace App\Mail\Partner;

use App\Models\PartnerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerApplicationRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PartnerApplication $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[SportGo] Thông báo kết quả xét duyệt hồ sơ đăng ký đối tác');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner.partner-application-rejected');
    }
}
