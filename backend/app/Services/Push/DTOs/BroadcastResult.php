<?php

namespace App\Services\Push\DTOs;

/**
 * Per-token outcome of one push broadcast, aggregated across every 500-token
 * multicast request FcmChannel made (push-notifications Slice 1).
 *
 * `$successes + $failures` can be lower than the number of tokens handed to
 * PushBroadcaster only if FCM omits items from its report; the recipient count
 * is therefore recorded separately on the log row rather than derived here.
 */
readonly class BroadcastResult
{
    public function __construct(
        public int $successes,
        public int $failures,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0);
    }
}
