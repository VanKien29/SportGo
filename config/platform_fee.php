<?php

return [
    'timezone' => env('PLATFORM_FEE_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'automatic_period_months' => 1,
    'automatic_lead_days' => 7,
    'default_due_day' => 5,
    'allowed_prepay_months' => [1, 3, 6, 9, 12],
    'allowed_deferred_months' => [1, 2, 3],
    'arrangement_proposal_hours' => 48,
    'trial_reminder_days' => 7,
];
