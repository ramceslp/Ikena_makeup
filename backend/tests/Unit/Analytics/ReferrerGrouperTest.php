<?php

namespace Tests\Unit\Analytics;

use App\Analytics\ReferrerGrouper;
use Tests\TestCase;

/**
 * ReferrerGrouperTest — covers App\Analytics\ReferrerGrouper
 * (visitor-analytics PR1b, spec "Traffic origin grouped by referrer":
 * sdd/visitor-analytics/spec).
 *
 * The raw referrer is used transiently here and is never stored — only
 * the resulting group name persists on visitor_events.referrer_group.
 */
class ReferrerGrouperTest extends TestCase
{
    public function test_instagram_hosts_and_subdomains_group_as_instagram(): void
    {
        $grouper = new ReferrerGrouper;

        $this->assertSame('instagram', $grouper->group('https://www.instagram.com/some-post/'));
        $this->assertSame('instagram', $grouper->group('https://l.instagram.com/?u=https://ikena.example'));
        $this->assertSame('instagram', $grouper->group('https://instagram.com/'));
    }

    public function test_google_hosts_and_subdomains_group_as_google(): void
    {
        $grouper = new ReferrerGrouper;

        $this->assertSame('google', $grouper->group('https://www.google.com/search?q=maquillaje'));
        $this->assertSame('google', $grouper->group('https://www.google.com.ec/'));
    }

    public function test_missing_or_blank_referrer_groups_as_direct(): void
    {
        $grouper = new ReferrerGrouper;

        $this->assertSame('direct', $grouper->group(null));
        $this->assertSame('direct', $grouper->group(''));
        $this->assertSame('direct', $grouper->group('   '));
    }

    public function test_unrecognised_host_groups_as_other(): void
    {
        $grouper = new ReferrerGrouper;

        $this->assertSame('other', $grouper->group('https://www.facebook.com/some-page'));
        $this->assertSame('other', $grouper->group('https://news.ycombinator.com/'));
    }

    public function test_a_malformed_referrer_groups_as_other_without_throwing(): void
    {
        $grouper = new ReferrerGrouper;

        $this->assertSame('other', $grouper->group('not a url at all !!'));
        $this->assertSame('other', $grouper->group('http:///no-host'));
    }
}
