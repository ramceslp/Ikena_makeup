<?php

namespace App\Analytics;

/**
 * BotDetector — flags automated clients by a case-insensitive User-Agent
 * substring match against config('analytics.bot_user_agents')
 * (visitor-analytics PR1b, design D9: sdd/visitor-analytics/design).
 *
 * Bots are FLAGGED, never dropped (locked decision 5) — the caller stores
 * is_bot=true and VisitorEvent::scopeReportable() excludes the row at
 * query time. This keeps a wrong list entry recoverable: a corrected list
 * re-classifies existing history instead of the loss being permanent, the
 * way silently discarding the event at ingestion would be.
 *
 * The raw User-Agent is used transiently here and is never stored on
 * visitor_events (design D1) — no visitor identity of any kind persists.
 */
class BotDetector
{
    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return false;
        }

        $haystack = strtolower($userAgent);

        foreach (config('analytics.bot_user_agents', []) as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
