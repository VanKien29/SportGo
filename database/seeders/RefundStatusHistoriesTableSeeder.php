<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RefundStatusHistoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('refund_status_histories') || ! Schema::hasTable('refunds')) {
            return;
        }

        $owner = User::query()->where('username', 'owner')->first();
        $admin = User::query()->where('username', 'admin')->first();

        // BK-CANC-01: pending_owner_confirmation (chờ duyệt)
        $this->seedByPaymentCode('PM-CANC-01', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
        ]);

        // BK-CANC-02: owner_confirmed (chờ admin)
        $this->seedByPaymentCode('PM-CANC-02', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân xác nhận đồng ý hoàn tiền.'],
        ]);

        // BK-CANC-03: admin_processing (đang hoàn)
        $this->seedByPaymentCode('PM-CANC-03', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân xác nhận đồng ý hoàn tiền.'],
            ['owner_confirmed', 'admin_processing', $admin?->id, 'admin', 'Admin gửi yêu cầu hoàn tiền qua kênh thanh toán.'],
        ]);

        // BK-CANC-04: completed (hoàn xong)
        $this->seedByPaymentCode('PM-CANC-04', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân xác nhận đồng ý hoàn tiền.'],
            ['owner_confirmed', 'admin_processing', $admin?->id, 'admin', 'Admin gửi yêu cầu hoàn tiền qua kênh thanh toán.'],
            ['admin_processing', 'completed', $admin?->id, 'admin', 'Cổng thanh toán báo hoàn tiền thành công.'],
        ]);

        // BK-CANC-05: owner_rejected (từ chối)
        $this->seedByPaymentCode('PM-CANC-05', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_rejected', $owner?->id, 'owner', 'Khách hủy quá sát giờ chơi nên không đủ điều kiện hoàn tiền.'],
        ]);

        // BK-CANC-06: pending_owner_confirmation (booking cọc, chờ duyệt)
        $this->seedByPaymentCode('PM-CANC-06', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền cọc.'],
        ]);

        // BK-CANC-08: completed (hoàn cọc xong)
        $this->seedByPaymentCode('PM-CANC-08', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền cọc.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân đồng ý hoàn cọc.'],
            ['owner_confirmed', 'admin_processing', $admin?->id, 'admin', 'Admin xử lý hoàn cọc.'],
            ['admin_processing', 'completed', $admin?->id, 'admin', 'Hoàn cọc thành công.'],
        ]);

        // BK-CANC-09: pending_owner_confirmation (chờ duyệt)
        $this->seedByPaymentCode('PM-CANC-09', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
        ]);

        // BK-CANC-10: admin_processing (đang xử lý)
        $this->seedByPaymentCode('PM-CANC-10', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_confirmed', $owner?->id, 'owner', 'Chủ sân đồng ý hoàn tiền.'],
            ['owner_confirmed', 'admin_processing', $admin?->id, 'admin', 'Admin gửi yêu cầu hoàn tiền qua kênh thanh toán.'],
        ]);
    }

    private function seedByPaymentCode(string $paymentCode, array $rows): void
    {
        $payment = Payment::query()->where('payment_code', $paymentCode)->first();
        $refund = $payment
            ? Refund::query()->where('payment_id', $payment->id)->first()
            : null;

        if (! $refund) {
            return;
        }

        foreach ($rows as [$oldStatus, $newStatus, $actorId, $actorType, $reason]) {
            RefundStatusHistory::query()->firstOrCreate(
                [
                    'refund_id' => $refund->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ],
                [
                    'changed_by' => $actorId,
                    'actor_type' => $actorType,
                    'metadata' => ['source' => 'RefundStatusHistoriesTableSeeder'],
                    'created_at' => now()->subDays(3),
                ],
            );
        }
    }
}
