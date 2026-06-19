<?php

namespace App\Mail\Partner;

class PartnerContractSignedByOwnerMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Cảm ơn bạn đã ký hợp đồng hợp tác - Chào mừng đối tác mới!';
    }

    protected function headline(): string
    {
        return 'Bạn đã ký hợp đồng thành công';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Số hợp đồng' => $this->value('contract_code'),
            'Tên cụm sân' => $this->value('venue_name'),
            'Thời gian ký' => $this->value('signed_at'),
            'Địa chỉ IP ký' => $this->value('ip_address'),
        ];
    }

    protected function messageText(): string
    {
        return 'Cảm ơn bạn đã hoàn tất ký hợp đồng hợp tác với SportGo. Tài khoản của bạn đã được cấp quyền Chủ sân và bạn có thể bắt đầu quản lý cụm sân ngay bây giờ. Hợp đồng sẽ được SportGo ký xác nhận trong thời gian sớm nhất để hoàn thiện bản hợp đồng chính thức.';
    }

    protected function action(): ?array
    {
        return $this->value('owner_url') ? ['label' => 'Vào trang quản lý sân', 'url' => $this->value('owner_url')] : null;
    }
}
