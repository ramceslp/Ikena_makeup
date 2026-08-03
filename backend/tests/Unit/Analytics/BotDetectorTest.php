<?php

namespace Tests\Unit\Analytics;

use App\Analytics\BotDetector;
use Tests\TestCase;

/**
 * BotDetectorTest — covers App\Analytics\BotDetector (visitor-analytics
 * PR1b, design D9: sdd/visitor-analytics/design).
 *
 * Bots are FLAGGED, never dropped (locked decision 5) — this class only
 * decides the flag; VisitorEvent::scopeReportable() does the excluding.
 */
class BotDetectorTest extends TestCase
{
    public function test_known_crawler_user_agents_are_flagged(): void
    {
        $detector = new BotDetector;

        $this->assertTrue($detector->isBot(
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
        ));
        $this->assertTrue($detector->isBot(
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'
        ));
        $this->assertTrue($detector->isBot('curl/8.4.0'));
        $this->assertTrue($detector->isBot('python-requests/2.31.0'));
    }

    public function test_real_browser_user_agents_are_not_flagged(): void
    {
        $detector = new BotDetector;

        $this->assertFalse($detector->isBot(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            .'(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ));
        $this->assertFalse($detector->isBot(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) '
            .'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
        ));
    }

    public function test_empty_or_absent_user_agent_is_not_flagged(): void
    {
        $detector = new BotDetector;

        $this->assertFalse($detector->isBot(null));
        $this->assertFalse($detector->isBot(''));
        $this->assertFalse($detector->isBot('   '));
    }
}
