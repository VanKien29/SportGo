<?php

namespace App\Mail\Partner;

class PartnerContractCompletedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Hợp đồng hợp tác đã hoàn thành - Bản lưu chính thức';
    }

    protected function headline(): string
    {
        return 'Hợp đồng hợp tác đã được hai bên ký đầy đủ';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Số hợp đồng' => $this->value('contract_code'),
            'Thời gian SportGo ký xác nhận' => $this->value('signed_at'),
            'Người ký xác nhận' => $this->value('admin_name'),
        ];
    }

    protected function messageText(): string
    {
        return 'Hợp đồng hợp tác đã được hai bên ký đầy đủ và có hiệu lực pháp lý. Bản hợp đồng chính thức đã được lưu trữ trong hồ sơ đối tác của bạn.';
    }

    protected function action(): ?array
    {
        return $this->value('download_url') ? ['label' => 'Tải hợp đồng chính thức', 'url' => $this->value('download_url')] : null;
    }
}
