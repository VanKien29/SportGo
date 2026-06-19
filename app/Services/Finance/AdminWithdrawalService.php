<?php

namespace App\Services\Finance;

use App\Models\OwnerWithdrawalRequest;
use App\Services\Wallets\OwnerWalletService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminWithdrawalService
{
    public function __construct(
        private readonly OwnerWalletService $wallets,
        private readonly FinanceReceiptService $receipts,
    ) {}

    public function updateStatus(OwnerWithdrawalRequest $withdrawal, string $status, array $context): OwnerWithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $status, $context): OwnerWithdrawalRequest {
            $withdrawal = OwnerWithdrawalRequest::query()
                ->with(['wallet', 'bankAccount'])
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status === $status && in_array($status, ['completed', 'rejected'], true)) {
                return $withdrawal;
            }

            $this->assertTransitionAllowed($withdrawal->status, $status);
            $statusBefore = $withdrawal->status;

            if ($status === 'approved') {
                if ($withdrawal->bankAccount?->status !== 'active') {
                    throw new RuntimeException('Tài khoản nhận tiền của chủ sân chưa hoạt động.');
                }

                $this->wallets->holdWithdrawal($withdrawal, [
                    'reason' => $context['reason'],
                    'admin_id' => $context['actor_id'] ?? null,
                ]);

                $withdrawal->reviewed_by = $context['actor_id'] ?? null;
                $withdrawal->reviewed_at = now();
                $withdrawal->review_note = $context['reason'];
                $withdrawal->status_reason = null;
            }

            if ($status === 'rejected') {
                if ($this->wallets->hasWithdrawalHold($withdrawal)) {
                    $this->wallets->releaseWithdrawal($withdrawal, [
                        'reason' => $context['reason'],
                        'admin_id' => $context['actor_id'] ?? null,
                    ]);
                }

                $withdrawal->reviewed_by = $context['actor_id'] ?? $withdrawal->reviewed_by;
                $withdrawal->reviewed_at = now();
                $withdrawal->status_reason = $context['reason'];
            }

            if ($status === 'completed') {
                $transferReference = trim((string) ($context['transfer_reference'] ?? ''));

                if ($transferReference === '') {
                    throw new RuntimeException('Cần mã giao dịch ngân hàng để hoàn tất yêu cầu rút.');
                }

                if (! $withdrawal->bankAccount || $withdrawal->bankAccount->status !== 'active') {
                    throw new RuntimeException('Tài khoản nhận tiền của chủ sân chưa hoạt động.');
                }

                if (! $this->wallets->hasWithdrawalHold($withdrawal)) {
                    $this->wallets->holdWithdrawal($withdrawal, [
                        'reason' => $context['reason'],
                        'admin_id' => $context['actor_id'] ?? null,
                        'source' => $context['source'] ?? 'admin',
                    ]);
                }

                $this->wallets->completeWithdrawal($withdrawal, [
                    'reason' => $context['reason'],
                    'admin_id' => $context['actor_id'] ?? null,
                    'source' => $context['source'] ?? 'admin',
                    'transfer_reference' => $transferReference,
                ]);

                $withdrawal->completed_by = $context['actor_id'] ?? null;
                $withdrawal->completed_at = now();
                $withdrawal->transfer_reference = $transferReference;
                $withdrawal->status_reason = null;
            }

            $withdrawal->status = $status;
            $withdrawal->metadata = array_merge($withdrawal->metadata ?? [], [
                'last_action_source' => $context['source'] ?? 'admin',
                'last_action_at' => now()->toIso8601String(),
                'last_status_before' => $statusBefore,
            ]);
            $withdrawal->save();

            if ($status === 'completed') {
                $this->receipts->createWithdrawalReceipt($withdrawal, $context['actor_id'] ?? null);
            }

            return $withdrawal->fresh();
        });
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = [
            'pending' => ['completed', 'approved', 'rejected'],
            'reviewing' => ['completed', 'approved', 'rejected'],
            'approved' => ['completed', 'rejected'],
            'rejected' => [],
            'completed' => [],
            'cancelled' => [],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new RuntimeException("Không thể chuyển trạng thái rút tiền từ {$from} sang {$to}.");
        }
    }
}
