<?php

namespace App\Console\Commands;

use App\Models\VenueCluster;
use App\Services\Payments\PlatformFeeNotificationService;
use App\Services\Payments\PlatformFeePeriodService;
use Illuminate\Console\Command;
use Throwable;

class GeneratePlatformFeePeriods extends Command
{
    protected $signature = 'platform-fees:generate
        {--cluster= : Chỉ xử lý một cụm sân cụ thể}';

    protected $description = 'Tự động sinh kỳ phí nền tảng hàng tháng cho các cụm sân active.';

    public function handle(
        PlatformFeePeriodService $periods,
        PlatformFeeNotificationService $notifications,
    ): int {
        $clusters = VenueCluster::query()
            ->where('status', 'active')
            ->when($this->option('cluster'), fn ($query, string $id) => $query->whereKey($id))
            ->orderBy('id')
            ->get();

        $stats = [
            'scanned' => $clusters->count(),
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        foreach ($clusters as $cluster) {
            try {
                $result = $periods->generateAutomaticPeriod($cluster);
                if ($result['status'] === 'created' && $result['ledger']) {
                    $stats['created']++;
                    $notifications->notifyCreated($result['ledger']);
                    $this->info(sprintf(
                        'Đã tạo kỳ #%s cho %s (%s - %s).',
                        $result['ledger']->id,
                        $cluster->name,
                        $result['ledger']->period_start?->format('d/m/Y'),
                        $result['ledger']->period_end?->format('d/m/Y'),
                    ));
                    continue;
                }

                $stats['skipped']++;
                $this->line(sprintf(
                    'Bỏ qua %s: %s.',
                    $cluster->name,
                    $result['reason'] ?: 'đã có kỳ phù hợp',
                ));
            } catch (Throwable $exception) {
                $stats['failed']++;
                report($exception);
                $this->error(sprintf(
                    'Không thể xử lý %s (#%s): %s',
                    $cluster->name,
                    $cluster->id,
                    $exception->getMessage(),
                ));
            }
        }

        $this->newLine();
        $this->table(
            ['Đã quét', 'Đã tạo', 'Bỏ qua', 'Lỗi'],
            [[
                $stats['scanned'],
                $stats['created'],
                $stats['skipped'],
                $stats['failed'],
            ]],
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
