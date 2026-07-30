<?php

namespace Tests\Feature\Push;

use App\Services\Push\AppDestinations;
use Tests\TestCase;

/**
 * The push destination catalogue is necessarily written down TWICE: once in
 * config/push_destinations.php (which decides what an admin may send) and once
 * in App/src/router/index.js (which decides what the app can actually open).
 * Nothing links them — PHP config is invisible to the Vite bundle and vice
 * versa — and vue-router 4 does NOT reject a push() to an unmatched path: it
 * resolves with an empty `matched` array, so the app renders its chrome around
 * an empty <RouterView>. Nothing throws, nothing is logged, and the admin's
 * history still says "sent". The feature just silently delivers a blank screen.
 *
 * This test is that missing link. It reads the REAL router source rather than a
 * fixture, so the duplication cannot drift unnoticed.
 *
 * The asymmetry below is deliberate. A route the app has but the catalogue does
 * not is merely an option the picker fails to offer — invisible to users. A
 * catalogue entry the app cannot open is a dead notification on every device.
 * Only the second direction fails the suite.
 *
 * Sibling of App/src/tests/androidNotificationChannel.test.js, which does the
 * same job across the JS↔Android resource boundary.
 */
class AppDestinationsSyncTest extends TestCase
{
    private const ROUTER_PATH = '../App/src/router/index.js';

    /**
     * @return array<int, string> every `path:` declared by the app's router
     */
    private function appRouterPaths(): array
    {
        $path = base_path(self::ROUTER_PATH);

        // A wrong path would otherwise surface as an empty match list, which
        // reads as "the app has no routes" and passes the emptiness checks
        // below for entirely the wrong reason.
        $this->assertFileExists(
            $path,
            "expected the app's router at {$path} (base_path: ".base_path().')'
        );

        preg_match_all(
            "/path:\s*'([^']+)'/",
            (string) file_get_contents($path),
            $matches
        );

        $paths = $matches[1] ?? [];

        $this->assertNotEmpty(
            $paths,
            'parsed no routes out of the app router — the `path:` declaration style probably changed'
        );

        return $paths;
    }

    /**
     * `/cursos/{slug}` (catalogue) and `/cursos/:slug` (vue-router) are the same
     * route written in two dialects.
     */
    private function toRouterSyntax(string $pattern): string
    {
        return str_replace('{slug}', ':slug', $pattern);
    }

    public function test_every_offered_destination_is_a_real_route_in_the_app(): void
    {
        $routerPaths = $this->appRouterPaths();

        foreach (app(AppDestinations::class)->all() as $destination) {
            $expected = $this->toRouterSyntax($destination['pattern']);

            $this->assertContains(
                $expected,
                $routerPaths,
                "config/push_destinations.php offers \"{$destination['label']}\" → {$destination['pattern']}, "
                ."but App/src/router/index.js has no route at {$expected}. A notification sent there "
                .'would open a blank screen on every device.'
            );
        }
    }

    /**
     * The catalogue is the admin's whole vocabulary now, so an empty or
     * unreachable one silently turns the deep-link feature off rather than
     * breaking loudly.
     */
    public function test_the_catalogue_is_not_empty(): void
    {
        $this->assertNotEmpty(app(AppDestinations::class)->all());
    }

    /**
     * The app has no admin or instructor surface at all — an invariant asserted
     * from the other side by App/src/tests/router.test.js. Offering such a
     * destination here would aim a broadcast at a screen that cannot exist.
     */
    public function test_no_destination_points_at_an_admin_or_instructor_screen(): void
    {
        foreach (app(AppDestinations::class)->all() as $destination) {
            $this->assertStringNotContainsStringIgnoringCase('admin', $destination['pattern']);
            $this->assertStringNotContainsStringIgnoringCase('instructor', $destination['pattern']);
        }
    }

    /**
     * The web panel and the app disagree on vocabulary — a course detail is
     * `/courses/{slug}` on the web and `/cursos/{slug}` in the app. Pasting a
     * URL from the browser's address bar is the likeliest way to aim a
     * notification at nowhere, and is exactly how this bug was reported. This
     * pins the rejection so a future "let's be lenient" change cannot quietly
     * restore the blank screen.
     */
    public function test_a_path_from_the_web_panel_is_not_accepted_as_an_app_destination(): void
    {
        $destinations = app(AppDestinations::class);

        $this->assertFalse($destinations->matchesAny('/courses/maquillaje-de-novias'));
        $this->assertFalse($destinations->matchesAny('/admin/noticias'));
        $this->assertFalse($destinations->matchesAny('/my-courses'));

        // …while the app's own dialect of the same screen is.
        $this->assertTrue($destinations->matchesAny('/cursos/maquillaje-de-novias'));
    }
}
