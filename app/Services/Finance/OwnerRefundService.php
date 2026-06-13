<?php

namespace App\Services\Finance;

use App\Models\Notification;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use App\Models\User;
use App\Services\Policies\RefundPolicyEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OwnerRefundService
{
    public function __construct(
        private readonly RefundPolicyEvaluator $refundPolicies,
    ) {}

    public function decide(
        Refund $refund,
        User $owner,
        string $decision,
        ?float $amount,
        string $note,
    ): Refund {
        return DB::transaction(function () use ($refund, $owner, $decision, $amount, $note): Refund {
            $refund = Refund::query()
                ->with(['booking.venueCluster', 'payment', 'customer'])
                ->whereKey($refund->id)
                ->whereHas('booking.venueCluster', fn ($query) => $query->where('owner_id', $owner->id))
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status !== 'pending_owner_confirmation') {
                throw ValidationException::withMessages([
                    'refund' => 'Yêu cầu này không còn chờ chủ sân xác nhận.',
                ]);
            }

            $oldStatus = $refund->status;
            $oldAmount = (float) $refund->amount;

            if ($decision === 'approve') {
                $policy = $this->refundPolicies->evaluate($refund, true, 'owner', $owner->id);
                $maximum = $this->maximumRefundAmount($refund, $policy);
                $approvedAmount = $amount ?? $oldAmount;

                if ($approvedAmount <= 0 || $approvedAmount > $maximum) {
                    throw ValidationException::withMessages([
                        'amount' => sprintf(
                            'Số tiền hoàn phải lớn hơn 0 và không vượt quá %sđ theo chính sách.',
                            number_format($maximum, 0, ',', '.')
                        ),
                    ]);
                }

                $refund->amount = round($approvedAmount, 2);
                $refund->status = 'owner_confirmed';
                $refund->status_reason = null;
            } else {
                $refund->status = 'owner_rejected';
                $refund->status_reason = $note;
            }

            $refund->owner_confirmed_by = $owner->id;
            $refund->owner_confirmed_at = now();
            $refund->owner_confirm_note = $note;
            $refund->save();

            if (Schema::hasTable('refund_status_histories')) {
                RefundStatusHistory::query()->create([
                    'refund_id' => $refund->id,
                    'old_status' => $oldStatus,
                    'new_status' => $refund->status,
                    'changed_by' => $owner->id,
                    'actor_type' => 'owner',
                    'reason' => $note,
                    'metadata' => [
                        'decision' => $decision,
                        'amount_before' => $oldAmount,
                        'amount_after' => (float) $refund->amount,
                    ],
                    'created_at' => now(),
                ]);
            }

            $this->notifyDecision($refund, $decision);

            return $refund->fresh([
                'booking.customer',
                'booking.venueCluster',
                'payment',
                'customer',
                'ownerConfirmedBy',
                'statusHistories.changedBy',
            ]);
        });
    }

    private function maximumRefundAmount(Refund $refund, array $policy): float
    {
        if (isset($policy['suggested_amount'])) {
            return max(0, (float) $policy['suggested_amount']);
        }

        return max(0, (float) ($refund->payment?->amount ?? $refund->amount));
    }

    private function notifyDecision(Refund $refund, string $decision): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $approved = $decision === 'approve';
        $bookingCode = $refund->booking?->booking_code ?: 'booking';

        if ($refund->customer_id) {
            Notification::query()->create([
                'user_id' => $refund->customer_id,
                'type' => $approved ? 'refund_owner_approved' : 'refund_owner_rejected',
                'title' => $approved ? 'Chủ sân đã đồng ý hoàn tiền' : 'Chủ sân từ chối hoàn tiền',
                'body' => $approved
                    ? "Yêu cầu hoàn tiền của {$bookingCode} đã được chủ sân xác nhận và chuyển sang bước xử lý."
                    : "Yêu cầu hoàn tiền của {$bookingCode} đã bị từ chối. Lý do: {$refund->status_reason}",
                'reference_type' => 'refund',
                'reference_id' => $refund->id,
                'data' => [
                    'booking_id' => $refund->booking_id,
                    'booking_code' => $bookingCode,
                    'status' => $refund->status,
                    'amount' => (float) $refund->amount,
                ],
            ]);
        }

        if (! $approved) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', [
                'super_admin',
                'admin',
                'finance_operator',
                'system_staff',
            ]))
            ->pluck('id')
            ->each(function (string $userId) use ($refund, $bookingCode): void {
                Notification::query()->create([
                    'user_id' => $userId,
                    'type' => 'refund_ready_for_admin',
                    'title' => 'Yêu cầu hoàn tiền chờ xử lý',
                    'body' => "Chủ sân đã xác nhận hoàn tiền cho {$bookingCode}.",
                    'reference_type' => 'refund',
                    'reference_id' => $refund->id,
                    'data' => [
                        'booking_id' => $refund->booking_id,
                        'booking_code' => $bookingCode,
                        'amount' => (float) $refund->amount,
                    ],
                ]);
            });
    }
}
