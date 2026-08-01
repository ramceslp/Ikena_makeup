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

    // =========================================================================
    // Anchor hardening — the profile allows a[target], which without a
    // matching rel lets the opened page reach back via window.opener
    // (reverse tabnabbing). HTMLPurifier can inject rel itself.
    // =========================================================================

    public function test_posts_purifier_profile_forces_target_noopener(): void
    {
        $profile = config('purifier.settings.posts');

        $this->assertTrue(
            (bool) ($profile['HTML.TargetNoopener'] ?? false),
            'a[target] without rel=noopener enables reverse tabnabbing.',
        );
    }

    public function test_posts_purifier_profile_forces_target_noreferrer(): void
    {
        $profile = config('purifier.settings.posts');

        $this->assertTrue((bool) ($profile['HTML.TargetNoreferrer'] ?? false));
    }
}
