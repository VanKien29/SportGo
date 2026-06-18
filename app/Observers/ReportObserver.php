<?php

namespace App\Observers;

use App\Models\Report;
use App\Models\User;
use App\Services\Admin\UserLockService;
use App\Services\Moderation\ViolationScoreService;
use App\Services\Policies\ModerationReportPolicyService;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class ReportObserver
{
    public function created(Report $report): void
    {
        try {
            app(ViolationScoreService::class)->handleReportCreated($report);

            $target = class_exists($report->reportable_type)
                ? ($report->reportable ?: $report->reportable_type)
                : $report->reportable_type;

            app(ModerationReportPolicyService::class)->evaluate(
                $target,
                $target instanceof Model ? null : (string) $report->reportable_id,
                $report->reporter
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        // UserLockService::checkAndAutoLock is no longer used here as ModerationReportPolicyService handles it
    }

    public function updated(Report $report): void
    {
        if ($report->wasChanged('status')) {
            try {
                $target = class_exists($report->reportable_type)
                    ? ($report->reportable ?: $report->reportable_type)
                    : $report->reportable_type;

                app(ModerationReportPolicyService::class)->evaluate(
                    $target,
                    $target instanceof Model ? null : (string) $report->reportable_id,
                    $report->reporter
                );
            } catch (Throwable $exception) {
                report($exception);
            }
            
        }
    }
}
