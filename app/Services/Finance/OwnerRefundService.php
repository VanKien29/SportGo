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
        private readonly AdminRefundService $adminRefunds,
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

            if (in_array($decision, ['approve', 'approve_cash'], true)) {
                $policy = $this->refundPolicies->evaluate($refund, true, 'owner', $owner->id);
                $approvedAmount = $this->policyRefundAmount($refund, $policy);

                if ($approvedAmount <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Chính sách hiện tại không có số tiền hoàn hợp lệ để xác nhận.',
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
                        'client_amount_ignored' => $amount,
                        'amount_before' => $oldAmount,
                        'amount_after' => (float) $refund->amount,
                    ],
                    'created_at' => now(),
                ]);
            }

            if (in_array($decision, ['approve', 'approve_cash'], true)) {
                $targetStatus = $decision === 'approve_cash' ? 'completed_cash' : 'completed';

                $refund = $this->adminRefunds->updateStatus($refund, $targetStatus, [
                    'actor_id' => $owner->id,
                    'reason' => $note !== ''
                        ? $note
                        : ($targetStatus === 'completed_cash'
                            ? 'Chủ sân đã hoàn tiền mặt trực tiếp cho khách.'
                            : 'Chủ sân xác nhận hoàn tiền, hệ thống tự hoàn vào ví khách hàng.'),
                    'source' => $targetStatus === 'completed_cash' ? 'owner_cash_refund' : 'owner_auto_wallet',
                    'gateway_refund_txn_id' => null,
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

    private function policyRefundAmount(Refund $refund, array $policy): float
    {
        if (isset($policy['suggested_amount'])) {
            return max(0, (float) $policy['suggested_amount']);
        }

        return max(0, (float) $refund->amount);
    }

    private function notifyDecision(Refund $refund, string $decision): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $approved = in_array($decision, ['approve', 'approve_cash'], true);
        $bookingCode = $refund->booking?->booking_code ?: 'booking';

        if ($refund->customer_id) {
            Notification::query()->create([
                'user_id' => $refund->customer_id,
                'type' => $approved ? 'refund_owner_approved' : 'refund_owner_rejected',
                'title' => $approved ? 'Chủ sân đã đồng ý hoàn tiền' : 'Chủ sân từ chối hoàn tiền',
                'body' => $approved
                    ? ($decision === 'approve_cash'
                        ? "Yêu cầu hoàn tiền của {$bookingCode} đã được chủ sân xác nhận hoàn tiền mặt tại sân."
                        : "Yêu cầu hoàn tiền của {$bookingCode} đã được chủ sân xác nhận và hoàn vào ví SportGo.")
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
    }
}
