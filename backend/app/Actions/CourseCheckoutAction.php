<?php

namespace App\Actions;

use App\Exceptions\AlreadyEnrolledException;
use App\Exceptions\CourseUnavailableException;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use Illuminate\Support\Str;

/**
 * CourseCheckoutAction — create a pending course Order + gateway checkout
 * session for one paid course.
 *
 * Extracted from CheckoutController::checkout() so the same authoritative
 * eligibility and pricing rules can be reused by the checkout-handoff redeem
 * endpoint, exactly like CartCheckoutAction (product_cart) and
 * CreateBookingAction (appointment) already are. Before this extraction the
 * course path was the only one of the three that a mobile client could not
 * hand off to the web, because its logic lived inline in the controller.
 *
 * Unlike the two sibling Actions this one needs no DB transaction: it writes
 * a single Order row and reserves nothing (no stock decrement, no slot
 * claim). The enrollment itself is created later, by
 * CheckoutController::confirm(), once the gateway approves.
 */
class CourseCheckoutAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @return array{order: Order, session: CheckoutSession}
     *
     * @throws CourseUnavailableException the course is unpublished or free (free courses enroll via POST /courses/{slug}/enroll)
     * @throws AlreadyEnrolledException the user already holds an Enrollment for this course
     */
    public function __invoke(User $user, Course $course): array
    {
        // Re-validated here rather than only at the HTTP boundary because the
        // checkout-handoff redeem endpoint calls this Action against a
        // snapshot that may be up to 10 minutes stale — the course could have
        // been unpublished since the handoff was created. The direct
        // POST /courses/{slug}/checkout route binds {course:slug} with no
        // published scope, so this check is load-bearing there too.
        if (! $course->is_published) {
            throw new CourseUnavailableException('This course is no longer available.');
        }

        if ((float) $course->price === 0.0) {
            throw new CourseUnavailableException(
                'This course is free. Use POST /courses/{slug}/enroll instead.'
            );
        }

        $alreadyEnrolled = $user->enrolledCourses()
            ->where('courses.id', $course->id)
            ->exists();

        if ($alreadyEnrolled) {
            throw new AlreadyEnrolledException('You are already enrolled in this course.');
        }

        // Create a pending order with a unique client_transaction_id (max 50 chars).
        // 'ORD-' (4) + 36 UUID chars = 40 chars — within the 50-char limit.
        $order = Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'client_transaction_id' => 'ORD-'.Str::uuid(),
            'gateway' => $this->gateway->name(),
            'amount_cents' => (int) round((float) $course->price * 100),
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Eager-load course so the gateway can access it without an extra query.
        $order->setRelation('course', $course);

        $session = $this->gateway->createCheckout($order);

        return ['order' => $order, 'session' => $session];
    }
}
