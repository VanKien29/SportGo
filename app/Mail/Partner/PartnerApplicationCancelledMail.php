<?php

namespace App\Mail\Partner;

use App\Models\PartnerApplication;

class PartnerApplicationCancelledMail extends PartnerWorkflowMail
{
    public function __construct(private readonly PartnerApplication $application)
    {
        parent::__construct();
    }

    protected function subjectText(): string
    {
        return '[SportGo] Hồ sơ đăng ký đối tác đã được hủy';
    }

    protected function headline(): string
    {
        return 'SportGo đã ghi nhận hủy hồ sơ đăng ký đối tác';
    }

    protected function fields(): array
    {
        return [
            'Người đăng ký' => $this->application->applicant_full_name ?: $this->application->user?->full_name,
            'Cụm sân' => $this->application->venue_name,
            'Thời gian hủy' => now()->format('H:i d/m/Y'),
            'Trạng thái' => 'Đã hủy',
            'Lý do' => $this->application->status_reason,
        ];
    }

    protected function messageText(): string
    {
        return 'Hồ sơ đăng ký đối tác/chủ sân của bạn đã dừng xử lý. Các tài liệu đã nộp vẫn được lưu trong lịch sử hồ sơ để phục vụ tra cứu khi cần. SportGo sẽ không cấp quyền chủ sân từ hồ sơ đã hủy này.';
    }

    protected function action(): ?array
    {
        return [
            'label' => 'Xem hồ sơ đối tác',
            'url' => url('/partner-application'),
        ];
    }
}
