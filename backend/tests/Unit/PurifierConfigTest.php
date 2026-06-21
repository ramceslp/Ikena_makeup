<?php

namespace Tests\Unit;

use Tests\TestCase;

class PurifierConfigTest extends TestCase
{
    public function test_posts_purifier_profile_has_safe_iframe_enabled(): void
    {
        $profile = config('purifier.settings.posts');

        $this->assertNotNull($profile, 'purifier.settings.posts config must exist');
        $this->assertTrue((bool) ($profile['HTML.SafeIframe'] ?? false));
    }

    public function test_posts_purifier_profile_has_youtube_and_vimeo_iframe_regexp(): void
    {
        $profile = config('purifier.settings.posts');
        $regexp  = $profile['URI.SafeIframeRegexp'] ?? '';

        $this->assertStringContainsString('youtube', $regexp);
        $this->assertStringContainsString('embed', $regexp);
        $this->assertStringContainsString('vimeo', $regexp);
        $this->assertStringContainsString('video', $regexp);
    }

    public function test_posts_purifier_profile_html_allowed_contains_iframe_and_anchor(): void
    {
        $profile     = config('purifier.settings.posts');
        $htmlAllowed = $profile['HTML.Allowed'] ?? '';

        $this->assertStringContainsString('iframe', $htmlAllowed);
        $this->assertStringContainsString('a[', $htmlAllowed);
    }
}
