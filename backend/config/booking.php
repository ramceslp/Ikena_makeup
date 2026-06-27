<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Business Timezone
    |--------------------------------------------------------------------------
    | All appointment and slot times are interpreted in this timezone.
    | Store as a single constant — do not scatter string literals in code.
    */
    'timezone' => 'America/Guayaquil',

    /*
    |--------------------------------------------------------------------------
    | Deposit Settings
    |--------------------------------------------------------------------------
    */
    'deposit' => [
        'default_percentage' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Venue Availability Settings
    |--------------------------------------------------------------------------
    | Controls how the VenueAvailabilityResolver generates candidate appointment
    | start times and enforces concurrency limits across agenda blocks.
    |
    | default_concurrency_limit  — hard cap when a block has concurrency_limit = null
    | default_soft_threshold     — warning threshold when a block has soft_threshold = null
    | warning_message            — surfaced in the API when is_near_capacity is true
    | candidate_granularity_minutes — interval (in minutes) between candidate starts
    | look_ahead_days            — how many calendar days ahead to resolve
    */
    'venue' => [
        'default_concurrency_limit'      => 1,
        'default_soft_threshold'         => null,
        'warning_message'                => 'Alta demanda — quedan pocos horarios',
        'candidate_granularity_minutes'  => 30,
        'look_ahead_days'                => 60,
    ],
];
