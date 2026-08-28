<?php

namespace App\Services\Payments;

use App\Jobs\SendPlatformFeeNotificationDeliveryJob;
use App\Models\PlatformFeeNotificationDelivery;
use App\Models\PlatformFeePaymentArrangement;
use App\Models\PlatformFeePlanVersion;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenuePlatformFeeLedger;

class PlatformFeeNotificationService
{
    public function notifyCreated(VenuePlatformFeeLedger $ledger): void
    {
        $ledger->loadMissing('venueCluster.owner');
        $owner = $ledger->venueCluster?->owner;
        if (! $owner) {
            return;
        }

        $remaining = round(max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0), 2);
        $event = match (true) {
            $ledger->status === 'settled_zero' || (float) $ledger->amount_due <= 0 => 'platform_fee.period_waived',
            $ledger->status === 'paid' && data_get($ledger->gateway_response, 'method') === 'owner_balance' => 'platform_fee.auto_paid',
            default => 'platform_fee.created',
        };
        [$title, $body] = $this->ledgerContent($ledger, $event, $remaining);

        $this->queueForUser(
            $owner,
            $event,
            1,
            $title,
            $body,
            '/owner/platform-fees?venue_cluster_id='.$ledger->venue_cluster_id,
            ['ledger_id' => $ledger->id, 'venue_cluster_id' => $ledger->venue_cluster_id],
            ledgerId: $ledger->id,
        );
    }

    public function queuePlanEvent(PlatformFeePlanVersion $plan, string $event): void
    {
        if (! in_array($event, ['scheduled', 'cancelled'], true)) {
            return;
        }

        [$title, $body] = $this->planContent($plan, $event);
        $owners = VenueCluster::query()
            ->whereIn('status', ['active', 'locked'])
            ->with('owner:id,full_name,email')
            ->get()
            ->pluck('owner')
            ->filter()
            ->unique('id');

        foreach ($owners as $owner) {
            $this->queueForUser(
                $owner,
                'platform_fee.plan_'.$event,
                (int) $plan->revision,
                $title,
                $body,
                '/owner/platform-fees',
                [
                    'plan_version_id' => $plan->id,
                    'plan_code' => $plan->code,
                    'effective_from' => $plan->effective_from?->toDateString(),
                ],
                planVersionId: $plan->id,
            );
        }
    }

    public function queueArrangementProposal(PlatformFeePaymentArrangement $arrangement): void
    {
        $arrangement->loadMissing(['owner', 'venueCluster']);
        if (! $arrangement->owner) {
            return;
        }

        $body = sprintf(
            'SportGo đề nghị thỏa thuận trả chậm %d kỳ cho cụm sân %s, tổng nghĩa vụ %s đ, hạn thanh toán %s. Đề nghị hết hiệu lực lúc %s.',
            (int) $arrangement->service_months,
            $arrangement->venueCluster?->name ?: 'cụm sân',
            number_format((float) $arrangement->total_amount, 0, ',', '.'),
            $arrangement->payment_due_date?->format('d/m/Y') ?: '-',
            $arrangement->expires_at?->format('d/m/Y H:i') ?: '-',
        );

        $this->queueForUser(
            $arrangement->owner,
            'platform_fee.arrangement_proposed',
            (int) $arrangement->terms_revision,
            'Có đề nghị trả chậm cần xác nhận',
            $body,
            '/owner/platform-fees?tab=arrangements&arrangement_id='.$arrangement->id,
            ['arrangement_id' => $arrangement->id, 'venue_cluster_id' => $arrangement->venue_cluster_id],
            arrangementId: $arrangement->id,
        );
    }

    public function queueAutoPayFailure(VenuePlatformFeeLedger $ledger, string $reason): void
    {
        $ledger->loadMissing('venueCluster.owner');
        $owner = $ledger->venueCluster?->owner;
        if (! $owner) {
            return;
        }

        $this->queueForUser(
            $owner,
            'platform_fee.auto_pay_failed',
            1,
            'Không thể tự động thanh toán phí nền tảng',
            sprintf(
                'Kỳ phí %s - %s của %s chưa được trừ số dư: %s. Bạn có thể thanh toán bằng chuyển khoản trước hạn %s.',
                $ledger->period_start?->format('d/m/Y') ?: '-',
                $ledger->period_end?->format('d/m/Y') ?: '-',
                $ledger->venueCluster?->name ?: 'cụm sân',
                $reason,
                $ledger->due_date?->format('d/m/Y') ?: '-',
            ),
            '/owner/platform-fees?venue_cluster_id='.$ledger->venue_cluster_id,
            ['ledger_id' => $ledger->id, 'venue_cluster_id' => $ledger->venue_cluster_id],
            ledgerId: $ledger->id,
        );
    }

    private function queueForUser(
        User $user,
        string $eventType,
        int $revision,
        string $title,
        string $body,
        string $actionUrl,
        array $payload,
        ?int $planVersionId = null,
        ?int $ledgerId = null,
        ?int $arrangementId = null,
    ): void {
        foreach (['in_app', 'email'] as $channel) {
            if ($channel === 'email' && ! $user->email) {
                continue;
            }

            $reference = $planVersionId ?: $ledgerId ?: $arrangementId ?: 0;
            $eventKey = implode(':', [$eventType, $reference, $revision, $user->id, $channel]);
            $delivery = PlatformFeeNotificationDelivery::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'event_type' => $eventType,
                    'event_revision' => $revision,
                    'plan_version_id' => $planVersionId,
                    'ledger_id' => $ledgerId,
                    'arrangement_id' => $arrangementId,
                    'recipient_user_id' => $user->id,
                    'channel' => $channel,
                    'destination' => $channel === 'email' ? $user->email : null,
                    'title' => $title,
                    'body' => $body,
                    'action_url' => $actionUrl,
                    'status' => 'pending',
                    'queued_at' => now(),
                    'payload' => $payload,
                ],
            );

            if ($delivery->wasRecentlyCreated || in_array($delivery->status, ['pending', 'failed'], true)) {
                SendPlatformFeeNotificationDeliveryJob::dispatch($delivery->id)->afterCommit();
            }
        }
    }

    private function planContent(PlatformFeePlanVersion $plan, string $event): array
    {
        $date = $plan->effective_from?->format('d/m/Y') ?: 'chưa xác định';

        return $event === 'scheduled'
            ? [
                'Bảng giá phí nền tảng sắp thay đổi',
                "SportGo đã lên lịch {$plan->name} ({$plan->code}) từ {$date}. Các kỳ đã phát hành giữ nguyên số tiền; hãy mở mục Phí nền tảng để xem cấu hình và kỳ đầu dự kiến.",
            ]
            : [
                'Đã hủy lịch thay đổi bảng giá phí nền tảng',
                "SportGo đã hủy lịch áp dụng {$plan->name} ({$plan->code}). Phiên bản đang có hiệu lực tiếp tục được dùng cho kỳ mới.",
            ];
    }

    private function ledgerContent(VenuePlatformFeeLedger $ledger, string $event, float $remaining): array
    {
        $cluster = $ledger->venueCluster?->name ?: 'cụm sân';
        $period = sprintf(
            '%s - %s',
            $ledger->period_start?->format('d/m/Y') ?: '-',
            $ledger->period_end?->format('d/m/Y') ?: '-',
        );

        return match ($event) {
            'platform_fee.period_waived' => [
                'Kỳ phí nền tảng đã được miễn phí',
                "Kỳ {$period} của {$cluster} đã hoàn tất với số tiền 0 đ. Bạn không cần thực hiện thanh toán.",
            ],
            'platform_fee.auto_paid' => [
                'Đã thanh toán phí nền tảng bằng số dư',
                "Kỳ {$period} của {$cluster} đã được tự động thanh toán bằng số dư chủ sân. Số còn phải trả: 0 đ.",
            ],
            default => [
                'Kỳ phí nền tảng mới cần thanh toán',
                sprintf(
                    'Kỳ %s của %s cần thanh toán %s đ, hạn %s.',
                    $period,
                    $cluster,
                    number_format($remaining, 0, ',', '.'),
                    $ledger->due_date?->format('d/m/Y') ?: '-',
                ),
            ],
        };
    }
}
