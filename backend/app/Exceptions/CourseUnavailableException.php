<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by CourseCheckoutAction when the course targeted by a checkout
 * request cannot be paid for at all:
 *   - the course is not published;
 *   - the course is free (price 0) — those enroll through
 *     POST /courses/{slug}/enroll instead, with no Order and no gateway.
 *
 * Maps to 422 at both HTTP boundaries (POST /courses/{slug}/checkout and the
 * checkout-handoff redeem endpoint) because the request itself describes a
 * purchase that can never succeed, not a transient conflict.
 *
 * Same rationale as ServiceUnavailableException: the direct checkout
 * controller could enforce these inline, but the checkout-handoff redeem
 * endpoint (mobile-capacitor-setup PR2) invokes the Action against a snapshot
 * that may be up to 10 minutes stale — the course could have been unpublished
 * or repriced to free since the handoff was created — so the Action re-runs
 * the checks itself instead of trusting the snapshot verbatim.
 */
class CourseUnavailableException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
