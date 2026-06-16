<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VideoUrl implements ValidationRule
{
    /**
     * Accepted patterns (case-insensitive):
     *   YouTube : youtube.com/watch?v=<id>
     *             youtu.be/<id>
     *             youtube.com/embed/<id>
     *   Vimeo   : vimeo.com/<digits>
     *             player.vimeo.com/video/<digits>
     *   MP4     : http(s) URL whose path ends in .mp4
     */
    private const PATTERNS = [
        // YouTube watch
        '#^https?://(www\.)?youtube\.com/watch\?.*v=[\w-]+#i',
        // YouTube short
        '#^https?://youtu\.be/[\w-]+#i',
        // YouTube embed
        '#^https?://(www\.)?youtube\.com/embed/[\w-]+#i',
        // Vimeo standard
        '#^https?://(www\.)?vimeo\.com/\d+#i',
        // Vimeo player
        '#^https?://player\.vimeo\.com/video/\d+#i',
        // Direct MP4 (http/https only; path before query/fragment ends in .mp4)
        '#^https?://[^\s]+\.mp4(\?[^\s]*)?$#i',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid video URL (YouTube, Vimeo, or a direct .mp4).');
            return;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return;
            }
        }

        $fail('The :attribute must be a valid video URL (YouTube, Vimeo, or a direct .mp4).');
    }
}
