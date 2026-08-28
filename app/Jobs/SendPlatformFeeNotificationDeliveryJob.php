<?php

namespace App\Jobs;

use App\Mail\PlatformFeeReminderMail;
use App\Models\Notification;
use App\Models\PlatformFeeNotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPlatformFeeNotificationDeliveryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $delivery = PlatformFeeNotificationDelivery::query()->find($this->deliveryId);
        if (! $delivery || $delivery->status === 'sent') {
            return;
        }

        $delivery->forceFill([
            'status' => 'sending',
            'attempts' => (int) $delivery->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            if ($delivery->channel === 'in_app') {
                Notification::query()->firstOrCreate(
                    [
                        'user_id' => $delivery->recipient_user_id,
                        'type' => $delivery->event_type,
                        'reference_type' => PlatformFeeNotificationDelivery::class,
                        'reference_id' => substr(hash('sha256', $delivery->event_key), 0, 64),
                    ],
                    [
                        'title' => $delivery->title,
                        'body' => $delivery->body,
                        'data' => array_merge($delivery->payload ?? [], [
                            'action_url' => $delivery->action_url,
                            'event_key' => $delivery->event_key,
                        ]),
                        'is_read' => false,
                    ],
                );
            } elseif ($delivery->channel === 'email') {
                if (! $delivery->destination) {
                    $delivery->forceFill([
                        'status' => 'skipped',
                        'last_error' => 'Người nhận chưa có email.',
                    ])->save();

                    return;
                }

                Mail::to($delivery->destination)->send(
                    new PlatformFeeReminderMail($delivery->title, $delivery->body),
                );
            } else {
                $delivery->forceFill([
                    'status' => 'skipped',
                    'last_error' => 'Kênh thông báo chưa được hỗ trợ.',
                ])->save();

                return;
            }

            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        PlatformFeeNotificationDelivery::query()
            ->whereKey($this->deliveryId)
            ->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);
    }
}
