<?php

namespace App\Mail\Partner;

class VenueChangeRequestRejectedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Hệ thống từ chối yêu cầu của bạn';
    }

    protected function headline(): string
    {
        return 'Yêu cầu chưa được SportGo xác nhận';
    }

    protected function fields(): array
    {
        return [
            'Loại yêu cầu' => $this->value('request_type'),
            'Cụm sân' => $this->value('cluster_name'),
            'Nội dung' => $this->value('summary'),
            'Lý do' => $this->value('reason'),
            'Thời gian xử lý' => $this->value('reviewed_at'),
            'Trạng thái' => $this->value('status_label', 'Đã từ chối'),
        ];
    }

    protected function messageText(): string
    {
        return $this->value('message', 'SportGo chưa thể xác nhận yêu cầu này. Vui lòng kiểm tra lý do và gửi lại yêu cầu mới nếu hệ thống hỗ trợ.');
    }
}
