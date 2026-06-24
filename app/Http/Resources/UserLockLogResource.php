<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLockLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'action_label' => $this->action === 'locked' ? 'Khóa tài khoản' : 'Mở khóa tài khoản',
            'reason' => $this->reason,
            'locked_by' => $this->locked_by,
            'locked_by_name' => $this->whenLoaded('lockedBy', fn () => $this->lockedBy?->full_name ?: $this->lockedBy?->username),
            'auto_triggered' => $this->auto_triggered,
            'performer_label' => $this->auto_triggered
                ? 'Hệ thống tự động'
                : ($this->whenLoaded('lockedBy', fn () => $this->lockedBy?->full_name ?: $this->lockedBy?->username) ?: 'Admin'),
            'lock_until' => $this->lock_until,
            'lock_until_label' => $this->lock_until ? $this->lock_until->format('d/m/Y H:i') : 'Vĩnh viễn',
            'policy_snapshot' => $this->policy_snapshot,
            'created_at' => $this->created_at,
        ];
    }
}
