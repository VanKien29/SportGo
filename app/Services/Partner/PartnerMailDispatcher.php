<?php

namespace App\Services\Partner;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PartnerMailDispatcher
{
    public function queue(User $recipient, Mailable $mail): void
    {
        if (! $recipient->email) {
            Log::warning('Partner workflow mail skipped: recipient has no email.', [
                'recipient_id' => $recipient->id,
                'mail' => $mail::class,
            ]);

            return;
        }

        try {
            Mail::to($recipient->email)->queue($mail);
            Log::info('Partner workflow mail queued.', [
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'mail' => $mail::class,
            ]);
        } catch (Throwable $exception) {
            Log::error('Partner workflow mail failed to queue.', [
                'recipient_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'mail' => $mail::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
