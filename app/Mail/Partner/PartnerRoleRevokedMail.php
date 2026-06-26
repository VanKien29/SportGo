<?php

namespace App\Mail\Partner;

class PartnerRoleRevokedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Thông báo: Quyền đối tác đã chấm dứt';
    }

    protected function headline(): string
    {
        return 'Quyền đối tác đã được thu hồi';
    }

    protected function fields(): array
    {
        return [
            'Tên chủ sân' => $this->value('owner_name'),
            'Thời gian thu hồi' => $this->value('revoked_at'),
            'Trạng thái hoàn tiền hiện tại' => $this->value('refund_status'),
        ];
    }

    protected function messageText(): string
    {
        return 'Tài khoản không còn quyền truy cập trang quản lý sân. Cảm ơn bạn đã là đối tác của SportGo. Nếu có thắc mắc về quyết toán hoặc muốn đăng ký lại trong tương lai, vui lòng liên hệ bộ phận hỗ trợ.';
    }
}
