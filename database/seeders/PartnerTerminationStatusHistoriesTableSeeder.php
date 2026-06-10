<?php

namespace Database\Seeders;

use App\Models\PartnerTerminationRequest;
use App\Models\PartnerTerminationStatusHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerTerminationStatusHistoriesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('partner_termination_status_histories') || ! Schema::hasTable('partner_termination_requests')) {
            return;
        }

        $this->seedHistory('TERM-MUTUAL-CG-001', [
            [null, 'submitted', 'owner', 'Chủ sân gửi yêu cầu chấm dứt hợp tác.'],
            ['submitted', 'approved', 'admin', 'Admin duyệt yêu cầu chấm dứt hai bên.'],
            ['approved', 'settlement_processing', 'admin', 'Bắt đầu quyết toán công nợ.'],
            ['settlement_processing', 'settlement_completed', 'admin', 'Đã hoàn tất quyết toán và tạo yêu cầu rút tiền.'],
        ]);

        $this->seedHistory('TERM-MUTUAL-CG-SETTLE', [
            [null, 'submitted', 'owner', 'Chủ sân gửi yêu cầu chấm dứt hợp tác hai bên.'],
            ['submitted', 'approved', 'admin', 'Admin duyệt yêu cầu, đang chuẩn bị biên bản thanh lý.'],
            ['approved', 'settlement_processing', 'admin', 'Đang đối soát quyết toán.'],
        ]);

        $this->seedHistory('TERM-OWNER-CG-001', [
            [null, 'submitted', 'owner', 'Chủ sân gửi đơn chấm dứt đơn phương.'],
            ['submitted', 'approved', 'admin', 'Admin xác nhận đơn chấm dứt của chủ sân.'],
        ]);

        $this->seedHistory('TERM-SPORTGO-CG-001', [
            [null, 'submitted', 'admin', 'SportGo tạo công văn chấm dứt đơn phương.'],
            ['submitted', 'approved', 'admin', 'Công văn chấm dứt đã được xác nhận.'],
            ['approved', 'transition_period', 'admin', 'Owner còn quyền xem dữ liệu trong thời gian chuyển tiếp 30 ngày.'],
        ]);

        $this->seedHistory('TERM-SPORTGO-CG-DONE', [
            [null, 'submitted', 'admin', 'SportGo tạo công văn chấm dứt đơn phương.'],
            ['submitted', 'approved', 'admin', 'Admin xác nhận chấm dứt đơn phương.'],
            ['approved', 'transition_period', 'admin', 'Bắt đầu thời gian chuyển tiếp 30 ngày.'],
            ['transition_period', 'settlement_processing', 'admin', 'Đối soát công nợ trước khi đóng quyền owner.'],
            ['settlement_processing', 'completed', 'admin', 'Hoàn tất quyết toán và thu quyền owner.'],
        ]);
    }

    private function seedHistory(string $terminationCode, array $rows): void
    {
        $request = PartnerTerminationRequest::query()->where('termination_code', $terminationCode)->first();

        if (! $request) {
            return;
        }

        foreach ($rows as [$oldStatus, $newStatus, $actorType, $reason]) {
            PartnerTerminationStatusHistory::query()->firstOrCreate(
                [
                    'partner_termination_request_id' => $request->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $reason,
                ],
                [
                    'changed_by' => $actorType === 'owner' ? $request->owner_id : $request->approved_by,
                    'actor_type' => $actorType,
                    'metadata' => ['source' => 'PartnerTerminationStatusHistoriesTableSeeder'],
                    'created_at' => now()->subDays(6),
                ],
            );
        }
    }
}
