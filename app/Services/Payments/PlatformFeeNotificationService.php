<?php

namespace App\Services\Payments;

use App\Jobs\SendPlatformFeeEmailJob;
use App\Models\Notification;
use App\Models\PlatformFeeEmailLog;
use App\Models\VenuePlatformFeeLedger;
use Illuminate\Support\Facades\Schema;

class PlatformFeeNotificationService
{
    public function notifyCreated(VenuePlatformFeeLedger $ledger): void
    {
        $ledger->loadMissing('venueCluster.owner');

        $cluster = $ledger->venueCluster;
        $owner = $cluster?->owner;
        if (! $cluster || ! $owner) {
            return;
        }

        $remaining = number_format(
            max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0),
            0,
            ',',
            '.',
        );
        $period = sprintf(
            '%s - %s',
            $ledger->period_start?->format('d/m/Y') ?: '-',
            $ledger->period_end?->format('d/m/Y') ?: '-',
        );
        $title = 'Kỳ phí nền tảng mới đã được tạo';
        $body = sprintf(
            'Kỳ phí của cụm sân %s (%s) đã được tạo tự động. Số tiền cần thanh toán: %s VND, hạn nộp: %s.',
            $cluster->name ?: 'cụm sân',
            $period,
            $remaining,
            $ledger->due_date?->format('d/m/Y') ?: '-',
        );
        $actionUrl = '/owner/platform-fees?venue_cluster_id=' . $cluster->id;

        if (Schema::hasTable('notifications')) {
            Notification::query()->firstOrCreate(
                [
                    'user_id' => $owner->id,
                    'type' => 'platform_fee.created',
                    'reference_type' => VenuePlatformFeeLedger::class,
                    'reference_id' => (string) $ledger->id,
                ],
                [
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'action_url' => $actionUrl,
                        'venue_cluster_id' => $cluster->id,
                        'ledger_id' => $ledger->id,
                    ],
                    'is_read' => false,
                ],
            );
        }

        $emailLog = PlatformFeeEmailLog::query()->firstOrCreate(
            [
                'ledger_id' => $ledger->id,
                'type' => 'created',
            ],
            [
                'venue_cluster_id' => $cluster->id,
                'email' => $owner->email,
                'subject' => $title,
                'content' => $body . "\nVui lòng đăng nhập SportGo Owner để xử lý.",
                'status' => $owner->email ? 'queued' : 'failed',
                'queued_at' => now(),
                'sent_at' => $owner->email ? null : now(),
                'error_reason' => $owner->email ? null : 'Chủ sân chưa có email.',
                'metadata' => [
                    'source' => 'system_auto',
                    'amount_due' => (float) $ledger->amount_due,
                    'period_start' => $ledger->period_start?->toDateString(),
                    'period_end' => $ledger->period_end?->toDateString(),
                ],
            ],
        );

        if ($owner->email && in_array($emailLog->status, ['queued', 'failed'], true)) {
            if ($emailLog->status === 'failed') {
                $emailLog->forceFill([
                    'status' => 'queued',
                    'queued_at' => now(),
                    'sent_at' => null,
                    'error_reason' => null,
                ])->save();
            }

            SendPlatformFeeEmailJob::dispatch($emailLog->id)->afterCommit();
        }
    }
}
