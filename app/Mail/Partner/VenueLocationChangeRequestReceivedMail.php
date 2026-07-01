<?php

namespace App\Mail\Partner;

class VenueLocationChangeRequestReceivedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Đã nhận yêu cầu thay đổi vị trí sân';
    }

    protected function headline(): string
    {
        return 'SportGo đã nhận yêu cầu thay đổi vị trí sân';
    }

    protected function fields(): array
    {
        return [
            'Cụm sân' => $this->value('cluster_name'),
            'Địa chỉ mới' => $this->value('new_address'),
            'Tọa độ mới' => $this->value('coordinates'),
            'Thời gian gửi' => $this->value('submitted_at'),
            'Trạng thái' => 'Đang chờ xét duyệt',
        ];
    }

    protected function messageText(): string
    {
        return 'Hệ thống đã nhận yêu cầu thay đổi vị trí sân của bạn. SportGo sẽ kiểm tra địa chỉ, tọa độ và tài liệu liên quan trước khi xác nhận.';
    }
}
