<?php

namespace App\Services\Partner;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PartnerMailDispatcher
{
    public function queue(User|string $recipient, Mailable $mail): void
    {
        $email = $recipient instanceof User ? $recipient->email : $recipient;
        $id = $recipient instanceof User ? $recipient->id : 'guest';

        if (! $email) {
            Log::warning('Partner workflow mail skipped: recipient has no email.', [
                'recipient_id' => $id,
                'mail' => $mail::class,
            ]);

            return;
        }

        try {
            Mail::to($email)->queue($mail);
            Log::info('Partner workflow mail queued.', [
                'recipient_id' => $id,
                'recipient_email' => $email,
                'mail' => $mail::class,
            ]);
        } catch (Throwable $exception) {
            Log::error('Partner workflow mail failed to queue.', [
                'recipient_id' => $id,
                'recipient_email' => $email,
                'mail' => $mail::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
