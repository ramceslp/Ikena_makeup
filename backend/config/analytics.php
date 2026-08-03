<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Known bot / crawler User-Agent substrings
    |--------------------------------------------------------------------------
    | Matched case-insensitively as a substring of the request's
    | User-Agent by App\Analytics\BotDetector. A hit sets is_bot=true.
    | Flagged events are excluded at query time by
    | VisitorEvent::scopeReportable() (design D7) but are never dropped
    | (locked decision 5) — a wrong entry here is recoverable by
    | correcting the list and re-running reports; a dropped row is not.
    */
    'bot_user_agents' => [
        'bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit',
        'googlebot', 'bingbot', 'yandexbot', 'duckduckbot', 'baiduspider',
        'ahrefsbot', 'semrushbot', 'mj12bot', 'petalbot', 'headlesschrome',
        'phantomjs', 'curl', 'wget', 'python-requests', 'go-http-client',
    ],

    /*
    |--------------------------------------------------------------------------
    | Referrer host -> traffic-origin group
    |--------------------------------------------------------------------------
    | App\Analytics\ReferrerGrouper matches the referrer's host against
    | each group's list below, including any subdomain of a listed host.
    | A referrer whose host matches none of these groups classifies as
    | 'other'; a missing/blank referrer classifies as 'direct' before
    | this map is even consulted.
    */
    'referrer_host_map' => [
        'instagram' => ['instagram.com'],
        'google' => ['google.com', 'google.com.ec'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention window
    |--------------------------------------------------------------------------
    | visitor-events:prune deletes rows older than this many months
    | (locked decision 3). Not consumed yet in PR1b — wired in PR2.
    */
    'retention_months' => 13,
];
