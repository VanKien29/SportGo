<?php

namespace App\Mail\Partner;

class PartnerUnilateralTerminationMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Thông báo chấm dứt hợp đồng hợp tác từ SportGo';
    }

    protected function headline(): string
    {
        return 'SportGo khởi tạo chấm dứt hợp tác';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Số hợp đồng' => $this->value('contract_code'),
            'Thời gian thông báo' => $this->value('issued_at'),
            'Lý do chấm dứt' => $this->value('reason'),
            'Ngày thu hồi quyền' => $this->value('revocation_date'),
            'Số tiền được hoàn trả' => $this->value('refund_amount'),
        ];
    }

    protected function messageText(): string
    {
        return 'SportGo đã ghi nhận quyết định chấm dứt hợp tác đơn phương theo lý do nêu trên. Các quyền quản lý sân sẽ được duy trì trong giai đoạn chuyển tiếp và tự động thu hồi vào ngày đã thông báo.';
    }
}
