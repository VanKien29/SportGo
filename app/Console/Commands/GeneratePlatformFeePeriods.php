<?php

namespace App\Console\Commands;

use App\Models\PlatformFeeAutomationResult;
use App\Models\PlatformFeeAutomationRun;
use App\Models\VenueCluster;
use App\Services\Payments\PlatformFeeNotificationService;
use App\Services\Payments\PlatformFeePeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class GeneratePlatformFeePeriods extends Command
{
    protected $signature = 'platform-fees:generate
        {--cluster= : Chỉ xử lý một cụm sân cụ thể}
        {--as-of= : Ngày nghiệp vụ Y-m-d}
        {--dry-run : Chỉ xem trước, không ghi kỳ phí}
        {--max-catch-up=24 : Số kỳ tối đa được bù cho mỗi cụm trong một lượt}';

    protected $description = 'Tự động sinh và bù các kỳ phí nền tảng còn thiếu.';

    public function handle(
        PlatformFeePeriodService $periods,
        PlatformFeeNotificationService $notifications,
    ): int {
        $asOf = $this->option('as-of')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->option('as-of'), config('platform_fee.timezone'))->startOfDay()
            : CarbonImmutable::today(config('platform_fee.timezone'));
        $dryRun = (bool) $this->option('dry-run');
        $maxCatchUp = max(1, min((int) $this->option('max-catch-up'), 120));
        $clusters = VenueCluster::query()
            ->whereIn('status', ['active', 'locked'])
            ->when($this->option('cluster'), fn ($query, string $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();
        $run = PlatformFeeAutomationRun::query()->create([
            'run_code' => sprintf('PFG-%s-%s', now()->format('YmdHisv'), bin2hex(random_bytes(3))),
            'job_type' => 'generate_periods',
            'as_of_date' => $asOf->toDateString(),
            'dry_run' => $dryRun,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => ['max_catch_up' => $maxCatchUp],
        ]);
        $stats = ['scanned' => $clusters->count(), 'created' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($clusters as $cluster) {
            $createdLedgers = [];
            $lastReason = null;
            $transactionStarted = false;

            try {
                if ($dryRun) {
                    DB::beginTransaction();
                    $transactionStarted = true;
                }

                for ($attempt = 0; $attempt < $maxCatchUp; $attempt++) {
                    $result = $periods->generateAutomaticPeriod($cluster, $asOf);
                    if ($result['status'] !== 'created' || ! $result['ledger']) {
                        $lastReason = $result['reason'] ?: 'Không có kỳ cần tạo.';
                        break;
                    }
                    $createdLedgers[] = $result['ledger'];
                }

                if ($transactionStarted) {
                    DB::rollBack();
                    $transactionStarted = false;
                }

                if ($createdLedgers === []) {
                    $stats['skipped']++;
                    $this->recordResult($run, $cluster, null, 'skipped', $lastReason ?: 'Không có kỳ cần tạo.', []);
                    $this->line("Bỏ qua {$cluster->name}: ".($lastReason ?: 'không có kỳ cần tạo').'.');
                    continue;
                }

                $stats['created'] += count($createdLedgers);
                foreach ($createdLedgers as $ledger) {
                    if (! $dryRun) {
                        $notifications->notifyCreated($ledger);
                    }
                    $this->recordResult(
                        $run,
                        $cluster,
                        $dryRun ? null : $ledger->id,
                        'created',
                        $dryRun ? 'Sẽ tạo kỳ phí.' : 'Đã tạo kỳ phí.',
                        [
                            'period_start' => $ledger->period_start?->toDateString(),
                            'period_end' => $ledger->period_end?->toDateString(),
                            'amount_due' => (float) $ledger->amount_due,
                            'plan_version_id' => $ledger->plan_version_id,
                        ],
                    );
                }

                $verb = $dryRun ? 'Sẽ tạo' : 'Đã tạo';
                $this->info(sprintf('%s %d kỳ cho %s.', $verb, count($createdLedgers), $cluster->name));
            } catch (Throwable $exception) {
                if ($transactionStarted && DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                report($exception);
                $stats['failed']++;
                $this->recordResult($run, $cluster, null, 'failed', $exception->getMessage(), []);
                $this->error(sprintf('Không thể xử lý %s (#%s): %s', $cluster->name, $cluster->id, $exception->getMessage()));
            }
        }

        $run->forceFill([
            'status' => $stats['failed'] > 0 ? 'completed_with_errors' : 'completed',
            'scanned_count' => $stats['scanned'],
            'created_count' => $stats['created'],
            'skipped_count' => $stats['skipped'],
            'failed_count' => $stats['failed'],
            'finished_at' => now(),
        ])->save();

        $this->table(['Đã quét', $dryRun ? 'Sẽ tạo' : 'Đã tạo', 'Bỏ qua', 'Lỗi'], [[
            $stats['scanned'],
            $stats['created'],
            $stats['skipped'],
            $stats['failed'],
        ]]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function recordResult(
        PlatformFeeAutomationRun $run,
        VenueCluster $cluster,
        ?int $ledgerId,
        string $result,
        string $reason,
        array $snapshot,
    ): void {
        PlatformFeeAutomationResult::query()->create([
            'automation_run_id' => $run->id,
            'venue_cluster_id' => $cluster->id,
            'ledger_id' => $ledgerId,
            'result' => $result,
            'reason' => mb_substr($reason, 0, 255),
            'snapshot' => $snapshot,
        ]);
    }
}
