<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\PracticeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TrustedProxyTest — the app runs behind a TLS-terminating proxy.
 *
 * Development is served through a Cloudflare tunnel (api.ramceslp.click ->
 * localhost:8000), and any production deployment will sit behind a proxy too.
 * The edge terminates TLS and forwards plain HTTP with X-Forwarded-Proto:
 * https, so without a trusted-proxy configuration Laravel believes every
 * request arrived over http.
 *
 * That is not cosmetic. Laravel's URL generator builds absolute URLs from the
 * INCOMING request's scheme and host — not from APP_URL — so every signed
 * practice-photo URL comes out as http://api.../api/submissions/... The
 * signature itself still validates (generation and validation agree on the
 * wrong scheme), but the SPA is served over https, and a browser blocks an
 * http image on an https page as mixed content. The photos simply never load,
 * with nothing in the Laravel log to show for it.
 *
 * The same misdetection suppresses HSTS in App\Http\Middleware\SecurityHeaders,
 * which keys off $request->secure().
 *
 * The proxy list is deliberately NOT '*': trusting any client's
 * X-Forwarded-For would let an attacker rotate that header to reset the
 * per-IP rate limiters in AppServiceProvider — the login throttle keys on
 * $request->ip(). Only a proxy on the loopback interface is trusted by
 * default, which is exactly where cloudflared runs.
 */
class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    private const API_HOST = 'api.ramceslp.click';

    /**
     * The URL Cloudflare actually hands Laravel: the public host, but plain
     * http, because TLS was terminated at the edge.
     *
     * Note this cannot be simulated by overriding config('app.url') in setUp —
     * the URL generator's root is taken from the bound request, which is built
     * once at boot, so a later config change does not move it.
     */
    private function proxiedUrl(string $path): string
    {
        return 'http://'.self::API_HOST.$path;
    }

    /**
     * A student, enrolled, with a practice submission on the lesson —
     * GET /api/lessons/{lesson} returns its photo URLs in `my_submission`.
     */
    private function studentWithSubmission(): array
    {
        Storage::fake('local');

        $instructor = User::factory()->instructor()->create();
        $course     = Course::factory()->create(['instructor_id' => $instructor->id, 'is_published' => true]);
        $section    = Section::factory()->create(['course_id' => $course->id]);
        $lesson     = Lesson::factory()->create(['section_id' => $section->id, 'is_practice' => true]);

        $student = User::factory()->create(['role' => 'student']);
        $student->enrolledCourses()->attach($course->id, ['price_paid' => 0]);

        $submission = PracticeSubmission::factory()->create([
            'lesson_id' => $lesson->id,
            'user_id'   => $student->id,
        ]);

        Storage::disk('local')->put($submission->before_path, 'photo-bytes');

        return [$student, $lesson];
    }

    private function photoUrlThroughProxy(): ?string
    {
        [$student, $lesson] = $this->studentWithSubmission();

        return $this->actingAs($student, 'sanctum')
            ->getJson($this->proxiedUrl("/api/lessons/{$lesson->id}"), ['X-Forwarded-Proto' => 'https'])
            ->assertStatus(200)
            ->json('data.my_submission.before_url');
    }

    public function test_photo_urls_generated_behind_the_proxy_use_https(): void
    {
        // The URL generator derives absolute URLs from the incoming request's
        // scheme. Get this wrong and the SPA — served over https — receives
        // http:// image URLs the browser refuses to load as mixed content.
        $url = $this->photoUrlThroughProxy();

        $this->assertNotNull($url, 'The lesson payload must expose the submission photo URL.');
        $this->assertStringStartsWith('https://', $url);
    }

    public function test_photo_urls_generated_behind_the_proxy_keep_the_public_host(): void
    {
        $this->assertStringStartsWith('https://'.self::API_HOST, $this->photoUrlThroughProxy());
    }

    public function test_the_request_is_reported_as_secure_behind_the_proxy(): void
    {
        // REMOTE_ADDR defaults to 127.0.0.1 in a test request — the loopback,
        // which is exactly where cloudflared runs.
        $this->getJson($this->proxiedUrl('/api/categories'), ['X-Forwarded-Proto' => 'https'])
            ->assertStatus(200)
            // SecurityHeaders only emits HSTS when $request->secure() is true.
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    // =========================================================================
    // Spoofing must not be possible from an untrusted source
    // =========================================================================

    public function test_forwarded_headers_from_an_untrusted_address_are_ignored(): void
    {
        // A direct caller from an arbitrary address must not be able to claim
        // the request was secure — nor, by the same mechanism, to spoof
        // X-Forwarded-For and reset the per-IP rate limiters.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->getJson($this->proxiedUrl('/api/categories'), ['X-Forwarded-Proto' => 'https'])
            ->assertStatus(200)
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_an_untrusted_client_cannot_spoof_its_ip_for_rate_limiting(): void
    {
        // The auth limiter keys on $request->ip(). If X-Forwarded-For were
        // honoured from anywhere, rotating it would hand out a fresh 5-attempt
        // budget per fake address — silently undoing the login throttle.
        $payload = ['email' => 'victim@example.com', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
                ->postJson('/api/login', $payload, ['X-Forwarded-For' => "198.51.100.{$i}"])
                ->assertStatus(401);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->postJson('/api/login', $payload, ['X-Forwarded-For' => '198.51.100.99'])
            ->assertStatus(429);
    }

    public function test_the_trusted_proxy_list_is_not_a_wildcard(): void
    {
        $proxies = config('app.trusted_proxies');

        $this->assertNotSame('*', $proxies, 'Trusting every proxy allows X-Forwarded-For spoofing, which resets the IP-keyed rate limiters.');
        $this->assertNotEmpty($proxies);
    }
}
