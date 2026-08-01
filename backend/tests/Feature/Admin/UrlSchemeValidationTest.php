<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UrlSchemeValidationTest — admin-supplied URLs must be http(s) only.
 *
 * Laravel's bare `url` rule accepts ~200 schemes (see the protocol list in
 * Illuminate\Support\Str::isUrl), including `data`, `blob` and `view-source`.
 * `javascript` is absent, so the classic payload never passed — but these
 * fields are rendered as anchor hrefs and image sources in the SPA, and the
 * only scheme set that is ever legitimate there is http/https.
 *
 * Locked with `url:http,https` on every URL-bearing admin/instructor field.
 */
class UrlSchemeValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function postPayload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Post con CTA',
            'body'         => '<p>Contenido.</p>',
            'type'         => 'noticia',
            'is_published' => false,
        ], $overrides);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rejectedSchemeProvider(): array
    {
        return [
            'data html'   => ['data://text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'view-source' => ['view-source://https://example.com'],
            'blob'        => ['blob://example.com/whatever'],
            'file'        => ['file://etc/passwd'],
            'ftp'         => ['ftp://example.com/payload'],
            'javascript'  => ['javascript:alert(1)'],
        ];
    }

    // =========================================================================
    // Post cta_url
    // =========================================================================

    #[DataProvider('rejectedSchemeProvider')]
    public function test_store_post_rejects_non_http_cta_url(string $url): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->postPayload(['cta_url' => $url]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cta_url');
    }

    public function test_store_post_accepts_an_https_cta_url(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->postPayload([
            'cta_url' => 'https://ikena.ramceslp.click/cursos/maquillaje',
        ]))->assertStatus(201);
    }

    public function test_store_post_accepts_an_http_cta_url(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->postPayload([
            'cta_url' => 'http://example.com/promo',
        ]))->assertStatus(201);
    }

    public function test_store_post_still_accepts_a_null_cta_url(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/posts', $this->postPayload(['cta_url' => null]))
            ->assertStatus(201);
    }

    #[DataProvider('rejectedSchemeProvider')]
    public function test_update_post_rejects_non_http_cta_url(string $url): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/posts', $this->postPayload())
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson("/api/admin/posts/{$created}", ['cta_url' => $url])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cta_url');
    }

    // =========================================================================
    // Course thumbnail
    // =========================================================================

    private function coursePayload(array $overrides = []): array
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        return array_merge([
            'title'         => 'Curso con thumbnail',
            'description'   => 'Descripción del curso.',
            'price'         => 100,
            // An admin creates courses on behalf of someone, so the owner is
            // an explicit validated input (StoreCourseRequest).
            'instructor_id' => $admin->id,
        ], $overrides);
    }

    #[DataProvider('rejectedSchemeProvider')]
    public function test_admin_store_course_rejects_non_http_thumbnail(string $url): void
    {
        $this->postJson('/api/admin/courses', $this->coursePayload(['thumbnail' => $url]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('thumbnail');
    }

    public function test_admin_store_course_accepts_an_https_thumbnail(): void
    {
        $this->postJson('/api/admin/courses', $this->coursePayload([
            'thumbnail' => 'https://example.com/thumb.jpg',
        ]))->assertStatus(201);
    }
}
