<?php

namespace App\Mail\Partner;

class PartnerApplicationReceivedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Xác nhận nhận đơn đăng ký đối tác';
    }

    protected function headline(): string
    {
        return 'SportGo đã nhận hồ sơ đăng ký đối tác';
    }

    protected function fields(): array
    {
        return [
            'Tên người dùng' => $this->value('user_name'),
            'Thời gian nộp đơn' => $this->value('submitted_at'),
            'Tên cụm sân đăng ký' => $this->value('venue_name'),
            'Trạng thái' => 'Đang chờ xét duyệt',
        ];
    }

    protected function messageText(): string
    {
        return 'Chúng tôi đã nhận được đơn đăng ký của bạn và đang trong quá trình xem xét. Thời gian xét duyệt thông thường là 3-5 ngày làm việc. Chúng tôi sẽ thông báo kết quả qua email này.';
    }

    protected function action(): ?array
    {
        return $this->value('status_url') ? ['label' => 'Xem trạng thái đơn', 'url' => $this->value('status_url')] : null;
    }
}
