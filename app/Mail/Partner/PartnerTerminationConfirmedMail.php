<?php

namespace App\Mail\Partner;

class PartnerTerminationConfirmedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Xác nhận chấm dứt hợp tác - Thông tin quyết toán';
    }

    protected function headline(): string
    {
        return 'Yêu cầu chấm dứt hợp tác đã được xác nhận';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Số hợp đồng' => $this->value('contract_code'),
            'Thời gian xác nhận' => $this->value('confirmed_at'),
            'Người xác nhận' => $this->value('admin_name'),
            'Tổng phí đã nộp' => $this->value('total_paid'),
            'Số tháng đã sử dụng' => $this->value('months_used'),
            'Số tháng chưa sử dụng' => $this->value('months_remaining'),
            'Số tiền được hoàn trả' => $this->value('refund_amount'),
            'Tài khoản nhận hoàn tiền' => $this->value('bank_account'),
            'Quyền chủ sân còn hiệu lực đến' => $this->value('revocation_date'),
        ];
    }

    protected function messageText(): string
    {
        return 'Yêu cầu hoàn tiền đã được ghi nhận và sẽ được xử lý trong vòng 7-14 ngày làm việc. Quyền quản lý sân của bạn sẽ chấm dứt vào ngày đã thông báo.';
    }
}
