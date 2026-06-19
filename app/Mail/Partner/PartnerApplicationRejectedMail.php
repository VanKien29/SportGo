<?php

namespace App\Mail\Partner;

class PartnerApplicationRejectedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Thông báo kết quả xét duyệt hồ sơ đăng ký đối tác';
    }

    protected function headline(): string
    {
        return 'Hồ sơ đối tác chưa đáp ứng yêu cầu';
    }

    protected function fields(): array
    {
        return [
            'Tên người dùng' => $this->value('user_name'),
            'Tên cụm sân đăng ký' => $this->value('venue_name'),
            'Thời gian xét duyệt' => $this->value('reviewed_at'),
            'Kết quả' => 'Hồ sơ chưa đáp ứng yêu cầu',
            'Lý do từ chối' => $this->value('reason'),
        ];
    }

    protected function messageText(): string
    {
        return 'Bạn có thể chỉnh sửa và nộp lại hồ sơ sau khi đã bổ sung đầy đủ các yêu cầu trên.';
    }

    protected function action(): ?array
    {
        return $this->value('resubmit_url') ? ['label' => 'Nộp lại hồ sơ', 'url' => $this->value('resubmit_url')] : null;
    }
}
