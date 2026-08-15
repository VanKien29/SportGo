<?php

return [
    'policy_version' => 'venue-booking-v1',

    // A venue complaint is only accepted while the customer's booking is active.
    'active_booking_statuses' => ['confirmed', 'checked_in'],
    'active_window_before_minutes' => 15,
    'active_window_after_minutes' => 60,

    // A second report for the same booking within this window is added to the
    // existing complaint through its reply/evidence thread.
    'duplicate_window_hours' => 24,

    'max_evidence_files' => 5,
    'max_evidence_size_kb' => 5120,
    'max_evidence_total_size_kb' => 20480,

    // Operational targets shown to complaint handlers.
    'first_response_due_hours' => 24,
    'resolution_due_days' => 3,
];
