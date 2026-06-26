<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class UserAccountLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public User $actor,
        public string $lockType,
        public string $reason,
        public ?string $lockedUntil = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SportGo - Thông báo khóa tài khoản',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-account-locked',
            with: [
                'user' => $this->user,
                'actor' => $this->actor,
                'lockTypeLabel' => $this->lockTypeLabel(),
                'reason' => $this->reason,
                'lockedUntilText' => $this->lockedUntilText(),
            ],
        );
    }

    private function lockTypeLabel(): string
    {
        return [
            'temporary' => 'Khóa tạm thời',
            'permanent' => 'Khóa vĩnh viễn',
            'auto' => 'Khóa tự động',
        ][$this->lockType] ?? 'Khóa tài khoản';
    }

    private function lockedUntilText(): ?string
    {
        if (! $this->lockedUntil) {
            return null;
        }

        return Carbon::parse($this->lockedUntil)
            ->timezone(config('app.timezone', 'Asia/Ho_Chi_Minh'))
            ->format('d/m/Y H:i');
    }
}
