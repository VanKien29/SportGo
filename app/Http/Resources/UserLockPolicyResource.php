<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLockPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auto_lock_enabled' => $this->auto_lock_enabled,
            'report_threshold' => $this->report_threshold,
            'lock_duration_hours' => $this->lock_duration_hours,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->full_name ?: $this->creator?->username),
            'updated_by' => $this->updated_by,
            'updated_by_name' => $this->whenLoaded('updater', fn () => $this->updater?->full_name ?: $this->updater?->username),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
