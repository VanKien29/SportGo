<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public string $purpose,
        public int $minutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === 'register'
                ? 'SportGo - Mã xác thực đăng ký tài khoản'
                : 'SportGo - Mã xác thực đặt lại mật khẩu',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth-otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'purpose' => $this->purpose,
                'minutes' => $this->minutes,
            ],
        );
    }
}
