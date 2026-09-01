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
        // Chỉ tạo các chuyển trạng thái thuộc luồng hiện hành.
        $this->seedByPaymentCode('PM-CANC-01', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
        ]);

        $this->seedByPaymentCode('PM-CANC-02', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'completed', $owner?->id, 'owner', 'Chủ sân xác nhận hoàn tiền vào ví SportGo.'],
        ]);

        $this->seedByPaymentCode('PM-CANC-03', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'completed_cash', $owner?->id, 'owner', 'Chủ sân hoàn tiền mặt trực tiếp tại sân.'],
        ]);

        $this->seedByPaymentCode('PM-CANC-04', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'completed', $owner?->id, 'owner', 'Chủ sân xác nhận hoàn tiền vào ví SportGo.'],
        ]);

        // BK-CANC-05: owner_rejected (từ chối)
        $this->seedByPaymentCode('PM-CANC-05', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
            ['pending_owner_confirmation', 'owner_rejected', $owner?->id, 'owner', 'Khách hủy quá sát giờ chơi nên không đủ điều kiện hoàn tiền.'],
        ]);

        $this->seedByPaymentCode('PM-CANC-06', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền cọc.'],
        ]);

        $this->seedByPaymentCode('PM-CANC-08', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền cọc.'],
            ['pending_owner_confirmation', 'completed', $owner?->id, 'owner', 'Chủ sân xác nhận hoàn cọc vào ví SportGo.'],
        ]);

        // BK-CANC-09: pending_owner_confirmation (chờ duyệt)
        $this->seedByPaymentCode('PM-CANC-09', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
        ]);

        $this->seedByPaymentCode('PM-CANC-10', [
            [null, 'pending_owner_confirmation', null, 'system', 'Khách gửi yêu cầu hoàn tiền.'],
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
