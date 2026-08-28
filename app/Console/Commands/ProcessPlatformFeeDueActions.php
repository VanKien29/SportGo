<?php

namespace App\Console\Commands;

use App\Models\PlatformFeeAutomationResult;
use App\Models\PlatformFeeAutomationRun;
use App\Models\VenuePlatformFeeLedger;
use App\Models\VenuePlatformFeeProfile;
use App\Services\Payments\PlatformFeeNotificationService;
use App\Services\Payments\PlatformFeeWalletService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ProcessPlatformFeeDueActions extends Command
{
    protected $signature = 'platform-fees:process-due
        {--as-of= : Ngày nghiệp vụ Y-m-d}
        {--cluster= : Chỉ xử lý một cụm sân}
        {--dry-run : Chỉ xem trước, không ghi dữ liệu}';

    protected $description = 'Tạo hold khi dịch vụ bắt đầu và tự thanh toán số dư vào ngày đến hạn.';

    public function handle(
        PlatformFeeWalletService $wallets,
        PlatformFeeNotificationService $notifications,
    ): int {
        $asOf = $this->option('as-of')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('as-of'), config('platform_fee.timezone'))->startOfDay()
            : CarbonImmutable::today(config('platform_fee.timezone'));
        $dryRun = (bool) $this->option('dry-run');
        $run = $this->startRun($asOf, $dryRun);
        $stats = ['scanned' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0];

        VenuePlatformFeeLedger::query()
            ->with('venueCluster.owner')
            ->whereIn('status', ['pending', 'overdue'])
            ->whereRaw('amount_paid < amount_due')
            ->whereDate('period_start', '<=', $asOf->toDateString())
            ->when($this->option('cluster'), fn ($query, string $id) => $query->where('venue_cluster_id', $id))
            ->orderBy('id')
            ->chunkById(100, function ($ledgers) use ($wallets, $notifications, $asOf, $dryRun, $run, &$stats): void {
                foreach ($ledgers as $ledger) {
                    $stats['scanned']++;
                    try {
                        $profile = VenuePlatformFeeProfile::query()
                            ->where('venue_cluster_id', $ledger->venue_cluster_id)
                            ->first();
                        $due = $ledger->due_date && $ledger->due_date->lte($asOf);
                        $result = 'skipped';
                        $reason = 'Chưa đến hạn tự thanh toán.';

                        if ($dryRun) {
                            $result = 'created';
                            $reason = $due && $profile?->auto_pay_from_balance
                                ? 'Sẽ tạo/cập nhật hold và thử thanh toán toàn bộ bằng số dư.'
                                : 'Sẽ tạo/cập nhật hold theo số dư an toàn.';
                        } else {
                            try {
                                $wallets->ensureLedgerHold($ledger, 'Tạm giữ số dư từ ngày kỳ dịch vụ bắt đầu.');
                                $result = 'created';
                                $reason = 'Đã đồng bộ khoản tạm giữ theo số dư an toàn.';
                            } catch (RuntimeException $exception) {
                                $reason = $exception->getMessage();
                            }

                            if ($due && $profile?->auto_pay_from_balance) {
                                try {
                                    $paid = $wallets->payFromBalance($ledger, (int) $ledger->venueCluster->owner_id, true);
                                    $notifications->notifyCreated($paid);
                                    $result = 'created';
                                    $reason = 'Đã tự động thanh toán toàn bộ bằng số dư chủ sân.';
                                } catch (RuntimeException $exception) {
                                    $reason = $exception->getMessage();
                                    $notifications->queueAutoPayFailure($ledger, $reason);
                                }
                            }
                        }

                        $stats[$result === 'created' ? 'created' : 'skipped']++;
                        $this->recordResult($run, $ledger, $result, $reason, $dryRun);
                    } catch (Throwable $exception) {
                        report($exception);
                        $stats['failed']++;
                        $this->recordResult($run, $ledger, 'failed', $exception->getMessage(), $dryRun);
                    }
                }
            });

        $run->forceFill([
            'status' => $stats['failed'] > 0 ? 'completed_with_errors' : 'completed',
            'scanned_count' => $stats['scanned'],
            'created_count' => $stats['created'],
            'skipped_count' => $stats['skipped'],
            'failed_count' => $stats['failed'],
            'finished_at' => now(),
        ])->save();

        $this->table(['Đã quét', 'Đã xử lý', 'Bỏ qua', 'Lỗi'], [[...array_values($stats)]]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function startRun(CarbonImmutable $asOf, bool $dryRun): PlatformFeeAutomationRun
    {
        return PlatformFeeAutomationRun::query()->create([
            'run_code' => sprintf('PFD-%s-%s', now()->format('YmdHisv'), bin2hex(random_bytes(3))),
            'job_type' => 'process_due',
            'as_of_date' => $asOf->toDateString(),
            'dry_run' => $dryRun,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function recordResult(
        PlatformFeeAutomationRun $run,
        VenuePlatformFeeLedger $ledger,
        string $result,
        string $reason,
        bool $dryRun,
    ): void {
        PlatformFeeAutomationResult::query()->create([
            'automation_run_id' => $run->id,
            'venue_cluster_id' => $ledger->venue_cluster_id,
            'ledger_id' => $ledger->id,
            'result' => $result,
            'reason' => mb_substr($reason, 0, 255),
            'snapshot' => [
                'dry_run' => $dryRun,
                'status' => $ledger->status,
                'period_start' => $ledger->period_start?->toDateString(),
                'due_date' => $ledger->due_date?->toDateString(),
                'amount_remaining' => round(max((float) $ledger->amount_due - (float) $ledger->amount_paid, 0), 2),
            ],
        ]);
    }
}
