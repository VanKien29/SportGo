<?php

namespace App\Mail\Partner;

class PartnerApplicationApprovedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Hồ sơ đã được duyệt - Vui lòng ký hợp đồng hợp tác';
    }

    protected function headline(): string
    {
        return 'Hồ sơ đối tác đã được SportGo chấp thuận';
    }

    protected function fields(): array
    {
        return [
            'Tên người dùng' => $this->value('user_name'),
            'Tên cụm sân đăng ký' => $this->value('venue_name'),
            'Thời gian duyệt' => $this->value('approved_at'),
            'Người duyệt' => $this->value('approved_by'),
            'Kết quả' => 'Hồ sơ đã được xét duyệt và chấp thuận',
            'Thời hạn ký' => $this->value('sign_deadline'),
        ];
    }

    protected function messageText(): string
    {
        return 'Chúc mừng! Hồ sơ đăng ký đối tác của bạn đã được SportGo chấp thuận. Vui lòng đọc kỹ nội dung hợp đồng và ký điện tử để hoàn tất thủ tục trở thành đối tác chính thức.';
    }

    protected function action(): ?array
    {
        return $this->value('sign_url') ? ['label' => 'Xem & ký hợp đồng', 'url' => $this->value('sign_url')] : null;
    }
}
