<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RefundsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('refunds') || ! Schema::hasTable('payments')) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->first();
        $payment = Payment::query()->where('payment_code', 'PMADMREF1')->first();

        if (! $payment) {
            return;
        }

        Refund::query()->updateOrCreate(
            [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
            ],
            [
                'amount' => 150000,
                'reason' => 'Khách hủy lịch trong thời gian được hoàn tiền.',
                'status' => 'pending_confirmation',
                'status_reason' => null,
                'processed_by' => $admin?->id,
                'processed_at' => null,
            ]
        );
    }
}
