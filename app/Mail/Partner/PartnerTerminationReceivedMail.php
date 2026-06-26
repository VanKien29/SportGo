<?php

namespace App\Mail\Partner;

class PartnerTerminationReceivedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Xác nhận đã nhận yêu cầu chấm dứt hợp tác';
    }

    protected function headline(): string
    {
        return 'SportGo đã nhận yêu cầu chấm dứt hợp tác';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Số hợp đồng' => $this->value('contract_code'),
            'Thời gian gửi yêu cầu' => $this->value('requested_at'),
            'Lý do đã cung cấp' => $this->value('reason'),
        ];
    }

    protected function messageText(): string
    {
        return 'Chúng tôi đã nhận được yêu cầu chấm dứt hợp tác của bạn. Bộ phận vận hành SportGo sẽ xem xét và phản hồi trong vòng 5 ngày làm việc. Trong thời gian chờ xử lý, tài khoản và cụm sân của bạn vẫn hoạt động bình thường.';
    }

    protected function action(): ?array
    {
        return $this->value('status_url') ? ['label' => 'Xem trạng thái yêu cầu', 'url' => $this->value('status_url')] : null;
    }
}
