<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerTerminatedMail extends Mailable
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
            subject: 'Thông báo: Chấm dứt hợp đồng đối tác',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: '<h3>Chào ' . $this->ownerName . ',</h3>
                        <p>Yêu cầu chấm dứt hợp đồng của bạn đã được Admin phê duyệt.</p>
                        <p>Hệ thống đã tự động tính toán lại số tiền hoàn cước (nếu có) và tạo yêu cầu Rút tiền về tài khoản ngân hàng mặc định của bạn.</p>
                        <p><b>Lưu ý quan trọng:</b> Tài khoản của bạn sẽ bị thu hồi quyền truy cập Chủ sân sau đúng 1 tháng kể từ hôm nay.</p>
                        <p>Cảm ơn bạn đã đồng hành cùng SportGo trong thời gian qua.</p>'
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
