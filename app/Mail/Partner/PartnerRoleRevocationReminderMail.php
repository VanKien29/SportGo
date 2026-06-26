<?php

namespace App\Mail\Partner;

class PartnerRoleRevocationReminderMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Nhắc nhở: Quyền quản lý sân sẽ chấm dứt sau 7 ngày';
    }

    protected function headline(): string
    {
        return 'Quyền quản lý sân sắp chấm dứt';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Ngày thu hồi quyền' => $this->value('revocation_date'),
            'Số hợp đồng' => $this->value('contract_code'),
        ];
    }

    protected function messageText(): string
    {
        return 'Vui lòng hoàn thiện các công việc còn dở như booking đang chờ xử lý, phân công nhân viên và bàn giao dữ liệu vận hành trước ngày thu hồi quyền.';
    }
}
