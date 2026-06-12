<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerRoleGrantedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ownerName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $ownerName)
    {
        $this->ownerName = $ownerName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Chào mừng bạn trở thành Đối tác của SportGo!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // For simplicity, we just use raw text or simple view
        return new Content(
            htmlString: '<h3>Chào ' . $this->ownerName . ',</h3>
                        <p>Hợp đồng đối tác của bạn đã được ký kết thành công!</p>
                        <p>Bạn đã được cấp quyền <b>Chủ sân</b> trên hệ thống SportGo.</p>
                        <p>Bây giờ bạn có thể đăng nhập vào trang quản trị để bắt đầu kinh doanh.</p>
                        <p>Chúc bạn kinh doanh hồng phát!</p>'
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
