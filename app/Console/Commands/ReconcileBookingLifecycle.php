<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Services\BookingService;
use App\Services\Bookings\BookingApprovalService;
use App\Services\Bookings\BookingLifecycleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBookingLifecycle extends Command
{
    protected $signature = 'bookings:reconcile-statuses {--dry-run : Only report changes without writing them}';

    protected $description = 'Đối soát vòng đời booking, gửi nhắc lịch và tự hoàn thành booking đủ điều kiện.';

    public function handle(BookingLifecycleService $lifecycle, BookingService $bookingService, BookingApprovalService $bookingApprovals): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now(config('app.business_timezone', 'Asia/Ho_Chi_Minh'));
        $summary = ['expired' => 0, 'no_show' => 0, 'completed' => 0, 'reminders' => 0, 'overdue' => 0];

        Booking::query()
            ->whereIn('status', ['pending_payment', 'pending_approval', 'confirmed', 'checked_in'])
            ->with(['venueCluster.bookingConfig', 'slotLocks', 'payments'])
            ->chunkById(100, function ($bookings) use ($now, $dryRun, $lifecycle, $bookingService, $bookingApprovals, &$summary): void {
                foreach ($bookings as $booking) {
                    $this->reconcileBooking($booking, $now, $dryRun, $lifecycle, $bookingService, $bookingApprovals, $summary);
                }
            });

        $this->line(sprintf(
            'expired=%d no_show=%d completed=%d reminders=%d overdue=%d%s',
            $summary['expired'], $summary['no_show'], $summary['completed'], $summary['reminders'], $summary['overdue'],
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }

    private function reconcileBooking(Booking $booking, Carbon $now, bool $dryRun, BookingLifecycleService $lifecycle, BookingService $bookingService, BookingApprovalService $bookingApprovals, array &$summary): void
    {
        if (! $booking->booking_date || ! $booking->start_time || ! $booking->end_time) return;

        $date = $booking->booking_date->format('Y-m-d');
        $timezone = config('app.business_timezone', 'Asia/Ho_Chi_Minh');
        $start = Carbon::parse($date . ' ' . $booking->start_time, $timezone);
        $end = Carbon::parse($date . ' ' . $booking->end_time, $timezone);
        $config = $booking->venueCluster?->bookingConfig;

        if ($booking->status === 'pending_approval') {
            if ($now->greaterThanOrEqualTo($bookingApprovals->approvalDeadline($booking))) {
                $summary['expired']++;
                if (! $dryRun) $bookingApprovals->expireIfDue($booking);
                return;
            }
        }

        if ($booking->status === 'pending_payment') {
            $deadline = $booking->payment_deadline_at
                ? $booking->payment_deadline_at->copy()->setTimezone($timezone)
                : $this->paymentDeadline($booking, $config, $start);
            if ($now->greaterThan($deadline)) {
                $summary['expired']++;
                if (! $dryRun) $this->expireBooking($booking, $lifecycle);
                return;
            }
        }

        if ($booking->status === 'confirmed') {
            if ($now->lessThan($start) && ! $booking->reminder_sent_at && $config) {
                $reminderAt = $start->copy()->subMinutes(max(0, (int) $config->reminder_before_minutes));
                if ($now->greaterThanOrEqualTo($reminderAt)) {
                    $summary['reminders']++;
                    if (! $dryRun) $this->sendReminder($booking);
                }
            }

            if ($now->greaterThan($end->copy()->addMinutes(60))) {
                $summary['no_show']++;
                if (! $dryRun) $lifecycle->transition($booking, 'no_show', 'check_in_missing', 'Booking đã quá giờ cho phép nhưng chưa check-in.');
            }
            return;
        }

        if ($booking->status === 'checked_in' && $now->greaterThan($end->copy()->addMinutes(15))) {
            if ($bookingService->outstandingAmount($booking) <= 0.009) {
                $summary['completed']++;
                if (! $dryRun) {
                    $completed = $lifecycle->transition($booking, 'completed', 'session_ended_auto', 'Booking tự hoàn thành sau khi buổi chơi kết thúc.');
                    $bookingService->syncMembershipForCompletedBooking($completed);
                }
            } else {
                $summary['overdue']++;
                if (! $dryRun) $this->notifyPaymentOverdue($booking);
            }
        }
    }

    private function paymentDeadline(Booking $booking, ?BookingConfig $config, Carbon $start): Carbon
    {
        $timezone = config('app.business_timezone', 'Asia/Ho_Chi_Minh');
        $lockDeadline = $booking->slotLocks->where('lock_type', 'auto')->sortBy('expires_at')->first()?->expires_at;
        if ($booking->status === 'pending_payment' && $lockDeadline) {
            return Carbon::parse($lockDeadline)->setTimezone($timezone);
        }

        $minutes = (int) ($config?->slot_hold_minutes ?? 20);
        $createdDeadline = Carbon::parse($booking->created_at)->setTimezone($timezone)->addMinutes($minutes);
        $start = $start->copy()->setTimezone($timezone);

        return $createdDeadline->lessThan($start) ? $createdDeadline : $start;
    }

    private function expireBooking(Booking $booking, BookingLifecycleService $lifecycle): void
    {
        DB::transaction(function () use ($booking, $lifecycle): void {
            $reason = $booking->status === 'pending_approval'
                ? 'Chủ sân không duyệt booking trong thời gian quy định.'
                : 'Booking hết thời gian chờ thanh toán.';
            $expired = $lifecycle->transition($booking, 'expired', $booking->status === 'pending_approval' ? 'owner_approval_timeout' : 'payment_timeout', $reason);

            Payment::query()->where('booking_id', $booking->id)->where('status', 'pending')->lockForUpdate()->get()->each(function (Payment $payment) use ($reason): void {
                $payment->update(['status' => 'failed']);
                PaymentLog::query()->create([
                    'payment_id' => $payment->id,
                    'event_type' => 'booking_lifecycle_expired',
                    'status_before' => 'pending',
                    'status_after' => 'failed',
                    'error_code' => 'booking_expired',
                    'error_message' => $reason,
                ]);
            });

            app(BookingService::class)->releaseVoucherUsageForBooking($expired, 'cancelled');
            $expired->slotLocks()->delete();
        });
    }

    private function sendReminder(Booking $booking): void
    {
        $booking->forceFill(['reminder_sent_at' => now()])->save();
        if (! $booking->customer_id) return;

        Notification::query()->firstOrCreate(
            ['user_id' => $booking->customer_id, 'type' => 'booking_reminder', 'reference_type' => 'booking', 'reference_id' => (string) $booking->id],
            [
                'title' => 'Sắp đến giờ chơi',
                'body' => 'Booking ' . $booking->booking_code . ' sắp bắt đầu. Vui lòng chuẩn bị check-in đúng giờ.',
                'data' => ['booking_id' => $booking->id, 'action_url' => '/booking/' . $booking->id],
                'is_read' => false,
            ],
        );
    }

    private function notifyPaymentOverdue(Booking $booking): void
    {
        $recipients = [];
        if ($booking->venueCluster?->owner_id) {
            $recipients[] = [
                'user_id' => $booking->venueCluster->owner_id,
                'type' => 'booking_payment_overdue_owner',
                'title' => 'Booking còn công nợ',
                'body' => 'Booking ' . $booking->booking_code . ' đã kết thúc nhưng vẫn còn khoản cần thu.',
                'action_url' => '/owner/bookings/' . $booking->id,
            ];
        }
        if ($booking->customer_id) {
            $recipients[] = [
                'user_id' => $booking->customer_id,
                'type' => 'booking_payment_overdue_customer',
                'title' => 'Booking chưa thanh toán đủ',
                'body' => 'Booking ' . $booking->booking_code . ' đã kết thúc nhưng vẫn còn khoản cần thanh toán cho sân.',
                'action_url' => '/booking/' . $booking->id,
            ];
        }

        foreach ($recipients as $recipient) {
            Notification::query()->firstOrCreate(
                [
                    'user_id' => $recipient['user_id'],
                    'type' => $recipient['type'],
                    'reference_type' => 'booking',
                    'reference_id' => (string) $booking->id,
                ],
                [
                    'title' => $recipient['title'],
                    'body' => $recipient['body'],
                    'data' => ['booking_id' => $booking->id, 'action_url' => $recipient['action_url']],
                    'is_read' => false,
                ],
            );
        }
    }
}
