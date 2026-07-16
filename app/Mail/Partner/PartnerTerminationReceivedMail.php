<?php

namespace App\Mail\Partner;

class PartnerTerminationReceivedMail extends PartnerWorkflowMail
{
    protected function subjectText(): string
    {
        return '[SportGo] Da nhan yeu cau cham dut hop tac';
    }

    protected function headline(): string
    {
        return 'SportGo da nhan yeu cau cham dut hop tac';
    }

    protected function fields(): array
    {
        return [
            'Ten chu san' => $this->value('owner_name'),
            'So hop dong' => $this->value('contract_code'),
            'Thoi gian gui yeu cau' => $this->value('requested_at'),
            'Ly do da cung cap' => $this->value('reason'),
        ];
    }

    protected function messageText(): string
    {
        return 'Cum san se tam dung nhan booking moi. Ban van co the vao trang chu san o che do han che de xu ly booking, hoan tien, rut tien va theo doi ho so cham dut.';
    }

    protected function action(): ?array
    {
        return $this->value('status_url') ? ['label' => 'Xem trang thai yeu cau', 'url' => $this->value('status_url')] : null;
    }
}
