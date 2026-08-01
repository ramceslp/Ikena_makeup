<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MeetingUrl implements ValidationRule
{
    /**
     * Accepted patterns (case-insensitive):
     *   Google Meet : meet.google.com/<code>
     *   Zoom        : zoom.us/j/<digits>          (incl. vanity subdomains)
     *                 zoom.us/my/<room>
     *                 zoom.us/w/<digits>
     *   MS Teams    : teams.microsoft.com/l/meetup-join/<...>
     *                 teams.live.com/meet/<...>
     *
     * Deliberately a whitelist, mirroring App\Rules\VideoUrl: an arbitrary
     * https URL here would let an author paste a phishing link that students
     * are told to trust and click at a scheduled time.
     */
    private const PATTERNS = [
        // Google Meet
        '#^https://meet\.google\.com/[\w-]+#i',
        // Zoom join / webinar / personal room, including vanity subdomains
        '#^https://([\w-]+\.)?zoom\.us/(j|w|my)/[\w.-]+#i',
        // Microsoft Teams
        '#^https://teams\.microsoft\.com/l/meetup-join/[^\s]+#i',
        '#^https://teams\.live\.com/meet/[^\s]+#i',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid Google Meet, Zoom, or Microsoft Teams link.');
            return;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return;
            }
        }

        $fail('The :attribute must be a valid Google Meet, Zoom, or Microsoft Teams link.');
    }
}
