<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VenueComplaintMail extends Mailable
{
    use Queueable, SerializesModels;

    public $content;

    /**
     * Create a new message instance.
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SportGo] Thông báo: Xác nhận khiếu nại liên quan đến sân/cụm sân của bạn',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Kính gửi Quý đối tác,</p>
                        <p>SportGo đã tiếp nhận và xác nhận một khiếu nại liên quan đến sân/cụm sân của bạn với nội dung: <strong>' . e($this->content) . '</strong>.</p>
                        <p>Rất mong Quý đối tác rà soát lại hoạt động vận hành và thực hiện đúng quy chuẩn dịch vụ, quy định cộng đồng và chính sách hiện hành của SportGo.</p>
                        <p>Nếu Quý đối tác cần phản hồi hoặc cung cấp thêm thông tin, vui lòng phản hồi lại email này để được hỗ trợ.</p>
                        <br/>
                        <p>Trân trọng,<br/>
                        Đội ngũ SportGo</p>'
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
