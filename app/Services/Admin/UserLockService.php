<?php

namespace App\Services\Admin;

use App\Events\UserLockedEvent;
use App\Events\UserUnlockedEvent;
use App\Models\User;
use App\Models\UserLockLog;
use App\Models\UserLockPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserLockService
{
    /**
     * Khóa tài khoản thủ công bởi admin.
     *
     * @param  User      $target        User bị khóa
     * @param  User      $admin         Admin thực hiện
     * @param  string    $reason        Lý do khóa (bắt buộc)
     * @param  int|null  $durationHours Thời hạn khóa (giờ), null = vĩnh viễn
     *
     * @throws ValidationException
     */
    public function lockManual(User $target, User $admin, string $reason, ?int $durationHours = null): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Lý do khóa không được để trống.',
            ]);
        }

        DB::transaction(function () use ($target, $admin, $reason, $durationHours): void {
            $lockUntil = $durationHours ? now()->addHours($durationHours) : null;
            $lockType = $durationHours ? 'temporary' : 'permanent';

            $target->forceFill([
                'status' => 'locked',
                'is_locked' => true,
                'lock_type' => $lockType,
                'status_reason' => $reason,
                'locked_at' => now(),
                'locked_until' => $lockUntil,
                'locked_by' => $admin->id,
            ])->save();

            $log = UserLockLog::query()->create([
                'user_id' => $target->id,
                'action' => 'locked',
                'reason' => $reason,
                'locked_by' => $admin->id,
                'auto_triggered' => false,
                'lock_until' => $lockUntil,
                'policy_snapshot' => null,
                'created_at' => now(),
            ]);

            // Thu hồi toàn bộ token
            $target->tokens()->delete();

            UserLockedEvent::dispatch($target, $log);
        });
    }

    /**
     * Khóa tài khoản tự động theo policy.
     *
     * @param  User            $target User bị khóa
     * @param  UserLockPolicy  $policy Policy đang active
     */
    public function lockAutomatic(User $target, UserLockPolicy $policy): void
    {
        if (! $policy->auto_lock_enabled) {
            return;
        }

        DB::transaction(function () use ($target, $policy): void {
            $lockUntil = $policy->lock_duration_hours
                ? now()->addHours($policy->lock_duration_hours)
                : null;
            $lockType = $policy->lock_duration_hours ? 'auto' : 'permanent';

            $policySnapshot = [
                'id' => $policy->id,
                'auto_lock_enabled' => $policy->auto_lock_enabled,
                'report_threshold' => $policy->report_threshold,
                'lock_duration_hours' => $policy->lock_duration_hours,
            ];

            $target->forceFill([
                'status' => 'locked',
                'is_locked' => true,
                'lock_type' => $lockType,
                'status_reason' => 'Khóa tự động: vượt ngưỡng ' . $policy->report_threshold . ' báo cáo.',
                'locked_at' => now(),
                'locked_until' => $lockUntil,
                'locked_by' => null,
            ])->save();

            $log = UserLockLog::query()->create([
                'user_id' => $target->id,
                'action' => 'locked',
                'reason' => 'Khóa tự động: vượt ngưỡng ' . $policy->report_threshold . ' báo cáo.',
                'locked_by' => null,
                'auto_triggered' => true,
                'lock_until' => $lockUntil,
                'policy_snapshot' => $policySnapshot,
                'created_at' => now(),
            ]);

            $target->tokens()->delete();

            UserLockedEvent::dispatch($target, $log);
        });
    }

    /**
     * Mở khóa tài khoản.
     *
     * @param  User    $target User được mở khóa
     * @param  User    $admin  Admin thực hiện
     * @param  string  $reason Lý do mở khóa
     */
    public function unlock(User $target, User $admin, string $reason): void
    {
        DB::transaction(function () use ($target, $admin, $reason): void {
            $target->forceFill([
                'status' => 'active',
                'is_locked' => false,
                'lock_type' => null,
                'status_reason' => null,
                'locked_at' => null,
                'locked_until' => null,
                'locked_by' => null,
            ])->save();

            $log = UserLockLog::query()->create([
                'user_id' => $target->id,
                'action' => 'unlocked',
                'reason' => $reason,
                'locked_by' => $admin->id,
                'auto_triggered' => false,
                'lock_until' => null,
                'policy_snapshot' => null,
                'created_at' => now(),
            ]);

            UserUnlockedEvent::dispatch($target, $log);
        });
    }

    /**
     * Kiểm tra và tự động khóa nếu đủ điều kiện.
     * Gọi sau khi có report mới.
     *
     * @param  User  $target User cần kiểm tra
     */
    public function checkAndAutoLock(User $target): void
    {
        $policy = UserLockPolicy::getActive();

        // Không có policy active hoặc auto_lock tắt → bỏ qua
        if (! $policy || ! $policy->auto_lock_enabled) {
            return;
        }

        // User đang bị khóa rồi → không ghi log trùng
        if ($target->is_locked || $target->status === 'locked') {
            return;
        }

        // Đếm số report chưa resolve của user (reportable_type trỏ đến user)
        $unresolvedReportCount = DB::table('reports')
            ->whereIn('reportable_type', ['users', 'user', User::class])
            ->where('reportable_id', $target->id)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->count();

        if ($unresolvedReportCount >= $policy->report_threshold) {
            $this->lockAutomatic($target, $policy);
        }
    }
}
