<?php

namespace App\Analytics;

/**
 * ReferrerGrouper — classifies a raw HTTP referrer into one of four fixed
 * groups (visitor-analytics PR1b, spec "Traffic origin grouped by
 * referrer": sdd/visitor-analytics/spec).
 *
 * The raw referrer is used transiently here and never stored — only the
 * resulting group name persists on visitor_events.referrer_group (design
 * D1: no visitor identity, and a full referrer URL can itself leak a
 * visitor's prior browsing).
 *
 * A missing/blank referrer classifies as 'direct'; a referrer whose host
 * cannot be parsed, or that matches none of config('analytics.referrer_host_map'),
 * classifies as 'other' — never throws.
 */
class ReferrerGrouper
{
    public function group(?string $referrer): string
    {
        if ($referrer === null || trim($referrer) === '') {
            return 'direct';
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'other';
        }

        $host = strtolower($host);

        foreach (config('analytics.referrer_host_map', []) as $group => $knownHosts) {
            foreach ($knownHosts as $knownHost) {
                if ($host === $knownHost || str_ends_with($host, '.'.$knownHost)) {
                    return $group;
                }
            }
        }

        return 'other';
    }
}
