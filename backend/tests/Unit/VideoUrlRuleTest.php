<?php

namespace Tests\Unit;

use App\Rules\VideoUrl;
use PHPUnit\Framework\TestCase;

class VideoUrlRuleTest extends TestCase
{
    private function passes(string $value): bool
    {
        $passes = true;
        $rule = new VideoUrl();
        $rule->validate('video_url', $value, function () use (&$passes) {
            $passes = false;
        });
        return $passes;
    }

    // -------------------------------------------------------------------------
    // YouTube — valid forms
    // -------------------------------------------------------------------------

    public function test_youtube_watch_url_is_valid(): void
    {
        $this->assertTrue($this->passes('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_youtube_watch_url_without_www_is_valid(): void
    {
        $this->assertTrue($this->passes('https://youtube.com/watch?v=dQw4w9WgXcQ'));
    }

    public function test_youtu_be_short_url_is_valid(): void
    {
        $this->assertTrue($this->passes('https://youtu.be/dQw4w9WgXcQ'));
    }

    public function test_youtube_embed_url_is_valid(): void
    {
        $this->assertTrue($this->passes('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    }

    public function test_youtube_embed_url_without_www_is_valid(): void
    {
        $this->assertTrue($this->passes('https://youtube.com/embed/dQw4w9WgXcQ'));
    }

    // -------------------------------------------------------------------------
    // Vimeo — valid forms
    // -------------------------------------------------------------------------

    public function test_vimeo_video_url_is_valid(): void
    {
        $this->assertTrue($this->passes('https://vimeo.com/123456789'));
    }

    public function test_player_vimeo_url_is_valid(): void
    {
        $this->assertTrue($this->passes('https://player.vimeo.com/video/123456789'));
    }

    public function test_vimeo_url_with_http_is_valid(): void
    {
        $this->assertTrue($this->passes('http://vimeo.com/123456789'));
    }

    // -------------------------------------------------------------------------
    // Direct MP4 — valid forms
    // -------------------------------------------------------------------------

    public function test_direct_mp4_https_url_is_valid(): void
    {
        $this->assertTrue($this->passes('https://cdn.example.com/videos/lesson1.mp4'));
    }

    public function test_direct_mp4_http_url_is_valid(): void
    {
        $this->assertTrue($this->passes('http://cdn.example.com/videos/lesson1.mp4'));
    }

    public function test_mp4_url_with_query_string_is_valid(): void
    {
        $this->assertTrue($this->passes('https://cdn.example.com/lesson.mp4?token=abc'));
    }

    public function test_case_insensitive_mp4_extension_is_valid(): void
    {
        $this->assertTrue($this->passes('https://cdn.example.com/lesson.MP4'));
    }

    // -------------------------------------------------------------------------
    // Invalid — must be rejected
    // -------------------------------------------------------------------------

    public function test_random_https_url_is_invalid(): void
    {
        $this->assertFalse($this->passes('https://evil.com/x'));
    }

    public function test_plain_string_is_invalid(): void
    {
        $this->assertFalse($this->passes('not a url'));
    }

    public function test_youtube_channel_url_is_invalid(): void
    {
        $this->assertFalse($this->passes('https://www.youtube.com/channel/UCxxxxxx'));
    }

    public function test_vimeo_non_digit_path_is_invalid(): void
    {
        $this->assertFalse($this->passes('https://vimeo.com/channels/staffpicks'));
    }

    public function test_non_mp4_video_url_is_invalid(): void
    {
        $this->assertFalse($this->passes('https://cdn.example.com/video.webm'));
    }

    public function test_ftp_mp4_url_is_invalid(): void
    {
        $this->assertFalse($this->passes('ftp://cdn.example.com/video.mp4'));
    }

    public function test_empty_string_is_invalid(): void
    {
        $this->assertFalse($this->passes(''));
    }
}
