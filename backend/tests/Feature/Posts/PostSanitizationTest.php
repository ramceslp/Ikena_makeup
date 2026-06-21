<?php

namespace Tests\Feature\Posts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Adversarial sanitization matrix for Post body HTML.
 * Tests assert that dangerous HTML is stripped server-side while
 * legitimate embeds (YouTube/Vimeo) are preserved.
 */
class PostSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function storePostWithBody(string $body): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/admin/posts', [
            'title'        => 'Sanitization Test',
            'body'         => $body,
            'type'         => 'noticia',
            'is_published' => false,
        ]);
    }

    private function getStoredBody(string $body): ?string
    {
        $response = $this->storePostWithBody($body)->assertStatus(201);
        $postId   = $response->json('data.id');

        return \App\Models\Post::find($postId)?->body;
    }

    // =========================================================================
    // Dangerous HTML — must be stripped
    // =========================================================================

    public function test_script_tag_is_stripped(): void
    {
        Sanctum::actingAs($this->admin());

        $stored = $this->getStoredBody('<p>Hello</p><script>alert("xss")</script>');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('alert', $stored);
    }

    public function test_onerror_inline_handler_is_stripped(): void
    {
        Sanctum::actingAs($this->admin());

        $stored = $this->getStoredBody('<img src="x" onerror="alert(1)">');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function test_javascript_uri_in_href_is_stripped(): void
    {
        Sanctum::actingAs($this->admin());

        $stored = $this->getStoredBody('<a href="javascript:alert(1)">click me</a>');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('javascript:', $stored);
    }

    public function test_non_allowlisted_iframe_is_stripped(): void
    {
        Sanctum::actingAs($this->admin());

        $stored = $this->getStoredBody('<iframe src="https://evil.com/track"></iframe>');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('evil.com', $stored);
        // AutoFormat.RemoveEmpty removes the residual empty <iframe> left after src is stripped
        $this->assertStringNotContainsString('<iframe', $stored);
    }

    public function test_style_tag_is_stripped(): void
    {
        Sanctum::actingAs($this->admin());

        $stored = $this->getStoredBody('<style>body { color: red; }</style><p>Text</p>');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('<style', $stored);
    }

    public function test_form_tag_is_stripped(): void
    {
        Sanctum::actingAs($this->admin());

        $stored = $this->getStoredBody('<form action="http://evil.com"><input type="text"></form>');

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('<form', $stored);
    }

    // =========================================================================
    // Legitimate content — must be preserved
    // =========================================================================

    public function test_youtube_embed_iframe_is_preserved(): void
    {
        Sanctum::actingAs($this->admin());

        $youtubeHtml = '<iframe src="https://www.youtube.com/embed/abc123" width="560" height="315" frameborder="0" allowfullscreen allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"></iframe>';
        $stored      = $this->getStoredBody('<p>Watch:</p>' . $youtubeHtml);

        $this->assertNotNull($stored);
        $this->assertStringContainsString('youtube.com/embed/abc123', $stored);
        $this->assertStringContainsString('<iframe', $stored);
        // allowfullscreen and allow must survive sanitization (HTML5 attrs registered via custom definition)
        $this->assertStringContainsString('allowfullscreen', $stored);
        $this->assertStringContainsString('allow=', $stored);
    }

    public function test_vimeo_embed_iframe_is_preserved(): void
    {
        Sanctum::actingAs($this->admin());

        $vimeoHtml = '<iframe src="https://player.vimeo.com/video/456789" width="640" height="360" frameborder="0" allowfullscreen allow="autoplay; fullscreen; picture-in-picture"></iframe>';
        $stored    = $this->getStoredBody('<p>Video:</p>' . $vimeoHtml);

        $this->assertNotNull($stored);
        $this->assertStringContainsString('player.vimeo.com/video/456789', $stored);
        $this->assertStringContainsString('<iframe', $stored);
        // allowfullscreen and allow must survive sanitization (HTML5 attrs registered via custom definition)
        $this->assertStringContainsString('allowfullscreen', $stored);
        $this->assertStringContainsString('allow=', $stored);
    }

    public function test_allowed_formatting_tags_are_preserved(): void
    {
        Sanctum::actingAs($this->admin());

        $html   = '<h2>Título</h2><p><strong>Negrita</strong> y <em>cursiva</em>.</p><ul><li>Item</li></ul>';
        $stored = $this->getStoredBody($html);

        $this->assertNotNull($stored);
        $this->assertStringContainsString('<h2>', $stored);
        $this->assertStringContainsString('<strong>', $stored);
        $this->assertStringContainsString('<em>', $stored);
        $this->assertStringContainsString('<ul>', $stored);
        $this->assertStringContainsString('<li>', $stored);
    }

    // =========================================================================
    // Sanitization on update() path
    // =========================================================================

    public function test_update_body_is_sanitized_on_update_path(): void
    {
        Sanctum::actingAs($this->admin());

        // Create an existing post first
        $createResponse = $this->storePostWithBody('<p>Original safe content</p>')->assertStatus(201);
        $postId         = $createResponse->json('data.id');

        // Submit a malicious body via the update path (POST /api/admin/posts/{id})
        $updateResponse = $this->postJson("/api/admin/posts/{$postId}", [
            'body' => '<p>Updated</p><script>alert("xss-on-update")</script>',
        ])->assertStatus(200);

        $stored = \App\Models\Post::find($postId)?->body;

        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('alert', $stored);
        $this->assertStringContainsString('Updated', $stored);
    }

    // =========================================================================
    // base64 in body — rejected by validation BEFORE sanitization
    // =========================================================================

    public function test_base64_data_uri_in_body_returns_422_before_sanitization(): void
    {
        Sanctum::actingAs($this->admin());

        $this->storePostWithBody('<p>Hello</p><img src="data:image/png;base64,iVBORw0K...">')
             ->assertStatus(422)
             ->assertJsonValidationErrors(['body']);
    }
}
