<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\UserWallet;
use App\Models\UserWithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class UserWithdrawalPaymentService
{
    public function __construct(
        private readonly FinanceReceiptService $receipts,
    ) {}

    public function pay(
        UserWithdrawalRequest $withdrawal,
        ?User $actor,
        string $method,
        ?string $transferReference = null,
        ?string $note = null,
    ): UserWithdrawalRequest {
        if (! in_array($method, ['cash', 'bank_transfer'], true)) {
            throw new RuntimeException('Phương thức chi trả không hợp lệ.');
        }

        $transferReference = trim((string) $transferReference);
        if ($method === 'bank_transfer' && $transferReference === '') {
            throw new RuntimeException('Vui lòng nhập mã giao dịch khi xác nhận chuyển khoản.');
        }

        return DB::transaction(function () use ($withdrawal, $actor, $method, $transferReference, $note): UserWithdrawalRequest {
            $withdrawal = UserWithdrawalRequest::query()
                ->with(['payoutAccount'])
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status === 'paid') {
                return $withdrawal->fresh(['user', 'wallet', 'payoutAccount', 'approvedBy', 'paidBy', 'receipt']);
            }

            if (! in_array($withdrawal->status, ['pending', 'approved'], true)) {
                throw new RuntimeException('Chỉ được chi trả yêu cầu rút tiền đang chờ xác nhận.');
            }

            if ($method === 'bank_transfer') {
                $account = $withdrawal->payoutAccount;
                if (! $account || $account->status !== 'active' || blank($account->bank_account_number)) {
                    throw new RuntimeException('Tài khoản nhận tiền của người dùng chưa hợp lệ.');
                }
            }

            $wallet = UserWallet::query()
                ->whereKey($withdrawal->user_wallet_id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException('Không tìm thấy ví người dùng.');
            }

            if ($wallet->status !== 'active') {
                throw new RuntimeException('Ví người dùng đang bị khóa hoặc tạm ngưng.');
            }

            if (! $this->hasCompletedWithdrawalLedger($withdrawal)) {
                $this->debitWallet($wallet, $withdrawal, $actor, $method, $transferReference, $note);
            }

            $withdrawal->forceFill([
                'status' => 'paid',
                'approved_by' => $withdrawal->approved_by ?: $actor?->id,
                'approved_at' => $withdrawal->approved_at ?: now(),
                'paid_by' => $actor?->id,
                'paid_at' => now(),
                'payment_method' => $method,
                'transfer_reference' => $method === 'bank_transfer' ? $transferReference : null,
                'paid_note' => $note,
            ])->save();

            $this->receipts->createUserWithdrawalReceipt($withdrawal->fresh(['payoutAccount', 'user']), $actor?->id);

            return $withdrawal->fresh(['user', 'wallet', 'payoutAccount', 'approvedBy', 'paidBy', 'receipt']);
        });
    }

    private function hasCompletedWithdrawalLedger(UserWithdrawalRequest $withdrawal): bool
    {
        return DB::table('user_wallet_ledgers')
            ->where('reference_type', 'user_withdrawal')
            ->where('reference_id', $withdrawal->id)
            ->where('type', 'withdrawal')
            ->where('direction', 'debit')
            ->where('status', 'completed')
            ->exists();
    }

    private function debitWallet(
        UserWallet $wallet,
        UserWithdrawalRequest $withdrawal,
        ?User $actor,
        string $method,
        string $transferReference,
        ?string $note,
    ): void {
        $amount = round((float) $withdrawal->amount, 2);
        $balanceBefore = round((float) $wallet->balance, 2);
        $lockedBefore = round((float) $wallet->locked_balance, 2);
        $fromLocked = min($lockedBefore, $amount);
        $remaining = round($amount - $fromLocked, 2);

        if ($remaining > $balanceBefore + 0.0001) {
            throw new RuntimeException('Ví người dùng không đủ số dư để chi trả yêu cầu rút tiền.');
        }

        $wallet->forceFill([
            'locked_balance' => round($lockedBefore - $fromLocked, 2),
            'balance' => round($balanceBefore - $remaining, 2),
        ])->save();

        DB::table('user_wallet_ledgers')->insert([
            'id' => (string) Str::uuid(),
            'user_wallet_id' => $wallet->id,
            'transaction_code' => 'UWD-'.strtoupper(substr(hash('sha256', $withdrawal->id.'|paid'), 0, 32)),
            'type' => 'withdrawal',
            'direction' => 'debit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $wallet->balance,
            'reference_type' => 'user_withdrawal',
            'reference_id' => $withdrawal->id,
            'status' => 'completed',
            'note' => $this->ledgerNote($method, $transferReference, $note),
            'created_by' => $actor?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ledgerNote(string $method, string $transferReference, ?string $note): string
    {
        $label = $method === 'cash' ? 'Admin chi tiền mặt' : 'Admin chuyển khoản';
        $parts = [$label];

        if ($transferReference !== '') {
            $parts[] = 'Mã GD: '.$transferReference;
        }

        if (filled($note)) {
            $parts[] = trim((string) $note);
        }

        return implode(' - ', $parts);
    }
}
