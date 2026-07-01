<?php

namespace App\Mail\Partner;

class VenueChangeRequestApprovedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Hệ thống đã xác nhận yêu cầu của bạn';
    }

    protected function headline(): string
    {
        return 'Yêu cầu đã được SportGo xác nhận';
    }

    protected function fields(): array
    {
        return [
            'Loại yêu cầu' => $this->value('request_type'),
            'Cụm sân' => $this->value('cluster_name'),
            'Nội dung' => $this->value('summary'),
            'Thời gian xác nhận' => $this->value('reviewed_at'),
            'Trạng thái' => 'Đã xác nhận',
        ];
    }

    protected function messageText(): string
    {
        return 'SportGo đã xác nhận yêu cầu của bạn và cập nhật thông tin liên quan trên hệ thống. Vui lòng đăng nhập trang quản lý chủ sân để kiểm tra lại.';
    }
}
