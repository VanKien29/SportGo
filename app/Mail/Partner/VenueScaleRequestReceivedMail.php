<?php

namespace App\Mail\Partner;

class VenueScaleRequestReceivedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Đã nhận đơn yêu cầu mở rộng quy mô';
    }

    protected function headline(): string
    {
        return 'SportGo đã nhận yêu cầu mở rộng quy mô sân';
    }

    protected function fields(): array
    {
        return [
            'Cụm sân' => $this->value('cluster_name'),
            'Sân đề xuất' => $this->value('court_name'),
            'Loại sân' => $this->value('court_type_name'),
            'Thời gian gửi' => $this->value('submitted_at'),
            'Trạng thái' => 'Đang chờ xét duyệt',
        ];
    }

    protected function messageText(): string
    {
        return 'Hệ thống đã nhận đơn yêu cầu mở rộng quy mô của bạn. SportGo sẽ kiểm tra thông tin, minh chứng và phản hồi kết quả trên hệ thống/email.';
    }
}
