<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserLockLog;
use Illuminate\Foundation\Events\Dispatchable;

class UserLockedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly UserLockLog $log,
    ) {}
}
