<?php

namespace App\Jobs;

use App\Mail\PlatformFeeReminderMail;
use App\Models\PlatformFeeEmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPlatformFeeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $emailLogId)
    {
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $log = PlatformFeeEmailLog::query()->find($this->emailLogId);
        if (! $log || $log->status === 'sent') {
            return;
        }

        if (! $log->email) {
            $log->forceFill([
                'status' => 'failed',
                'sent_at' => now(),
                'error_reason' => 'Chủ sân chưa có email.',
            ])->save();

            return;
        }

        Mail::to($log->email)->send(new PlatformFeeReminderMail(
            $log->subject,
            $log->content ?: '',
        ));

        $log->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'error_reason' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        PlatformFeeEmailLog::query()
            ->whereKey($this->emailLogId)
            ->update([
                'status' => 'failed',
                'sent_at' => now(),
                'error_reason' => $exception->getMessage(),
            ]);
    }
}
