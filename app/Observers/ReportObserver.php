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

        // Kiểm tra và tự động khóa nếu đủ điều kiện (chỉ với report về user)
        try {
            if (in_array($report->reportable_type, ['users', 'user', User::class], true)) {
                $targetUser = User::query()->find($report->reportable_id);
                if ($targetUser) {
                    app(UserLockService::class)->checkAndAutoLock($targetUser);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
