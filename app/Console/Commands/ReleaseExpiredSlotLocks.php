<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingConfig;
use App\Models\Payment;
use App\Models\PaymentLog;
use App\Models\SlotLock;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredSlotLocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:release-expired-slot-locks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Giải phóng các slot giữ sân tạm thời đã quá hạn và chuyển trạng thái các đơn đặt sân chưa thanh toán sang expired.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $this->info('Đang bắt đầu quét các slot lock hết hạn tại thời điểm: '.$now->toDateTimeString());

        // Tìm tất cả các slot lock có expires_at bé hơn hoặc bằng thời điểm hiện tại
        $expiredLocks = SlotLock::where('lock_type', 'auto')
            ->where('expires_at', '<=', $now)
            ->get();

        if ($expiredLocks->isEmpty()) {
            $this->info('Không có slot lock nào hết hạn.');

            return 0;
        }

        $processedCount = 0;

        foreach ($expiredLocks as $lock) {
            DB::transaction(function () use ($lock, &$processedCount) {
                // Nếu lock có liên kết với một Booking
                if ($lock->booking_id) {
                    $booking = Booking::find($lock->booking_id);
                    // Nếu booking vẫn ở trạng thái chờ thanh toán (pending_payment), chuyển sang hết hạn (expired)
                    if ($booking && $booking->status === 'pending_payment') {
                        $slotHoldMinutes = (int) (BookingConfig::query()
                            ->where('venue_cluster_id', $booking->venue_cluster_id)
                            ->value('slot_hold_minutes') ?? 20);
                        $reason = "Thanh toán quá hạn {$slotHoldMinutes} phút.";

                        $booking->update([
                            'status' => 'expired',
                            'status_reason' => $reason,
                        ]);

                        app(BookingService::class)->releaseVoucherUsageForBooking($booking, 'cancelled');

                        Payment::query()
                            ->where('booking_id', $booking->id)
                            ->where('status', 'pending')
                            ->lockForUpdate()
                            ->get()
                            ->each(function (Payment $payment) use ($reason): void {
                                $payment->update(['status' => 'failed']);

                                PaymentLog::query()->create([
                                    'payment_id' => $payment->id,
                                    'event_type' => 'payment_hold_expired',
                                    'status_before' => 'pending',
                                    'status_after' => 'failed',
                                    'error_code' => 'slot_hold_expired',
                                    'error_message' => $reason,
                                ]);
                            });
                    }
                }

                // Xoá bản ghi Slot Lock
                $lock->delete();
                $processedCount++;
            });
        }

        $this->info("Đã giải phóng thành công {$processedCount} slot locks hết hạn.");

        return 0;
    }
}
