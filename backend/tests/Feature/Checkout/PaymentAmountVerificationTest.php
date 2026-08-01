<?php

namespace Tests\Feature\Checkout;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PaymentAmountVerificationTest — the server must verify WHAT WAS CAPTURED,
 * not merely that the gateway said "approved".
 *
 * PayPhone's PPaymentButtonBox is a client-side widget: createCheckout() builds
 * the config (amount included) and CheckoutController hands it straight to the
 * browser, which passes it to the widget. The amount that actually gets charged
 * is therefore the amount the CLIENT gave PayPhone.
 *
 * confirm() used to check only `statusCode === 3`. That made the whole catalog
 * free: tamper the amount in the browser, pay one cent, get a legitimately
 * approved transaction back, and the server would mark a $500 order paid and
 * create the enrollment.
 *
 * The gateway response shape is documented at
 * https://docs.payphone.app/cajita-de-pagos — `amount` comes back as an
 * INTEGER IN CENTS, the same unit as orders.amount_cents, so it is directly
 * comparable with no conversion.
 */
class PaymentAmountVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRM_URL = 'https://paymentbox.payphonetodoesposible.com/api/confirm';

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the real PayPhoneGateway, with its HTTP call faked.
        config([
            'services.payments.driver'                => 'payphone',
            'services.payments.payphone.token'        => 'test-bearer-token',
            'services.payments.payphone.store_id'     => 'store-001',
            'services.payments.payphone.confirm_url'  => self::CONFIRM_URL,
        ]);
    }

    /**
     * A $500 course order, pending payment.
     */
    private function pendingCourseOrder(): array
    {
        $student    = User::factory()->create();
        $instructor = User::factory()->instructor()->create();

        $course = Course::factory()->create([
            'instructor_id' => $instructor->id,
            'price'         => 500.00,
            'is_published'  => true,
        ]);

        $order = Order::factory()->create([
            'user_id'               => $student->id,
            'course_id'             => $course->id,
            'type'                  => 'course',
            'status'                => 'pending',
            'gateway'               => 'payphone',
            'client_transaction_id' => 'ORD-500-dollar-course',
            'amount_cents'          => 50_000,
            'currency'              => 'USD',
        ]);

        return [$student, $course, $order];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeGatewayResponse(Order $order, array $overrides = []): void
    {
        Http::fake([
            self::CONFIRM_URL => Http::response(array_merge([
                'statusCode'          => 3,
                'transactionStatus'   => 'Approved',
                'amount'              => $order->amount_cents,
                'currency'            => 'USD',
                'clientTransactionId' => $order->client_transaction_id,
                'transactionId'       => 23178284,
                'authorizationCode'   => 'W23178284',
            ], $overrides)),
        ]);
    }

    private function confirm(User $student, Order $order): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($student);

        return $this->postJson('/api/payments/confirm', [
            'id'                  => 23178284,
            'clientTransactionId' => $order->client_transaction_id,
        ]);
    }

    // =========================================================================
    // The attack
    // =========================================================================

    public function test_a_one_cent_capture_does_not_pay_a_five_hundred_dollar_order(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        // PayPhone genuinely approved — of one cent. The transaction is real;
        // it is simply not the transaction this order requires.
        $this->fakeGatewayResponse($order, ['amount' => 1]);

        $this->confirm($student, $order);

        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_a_one_cent_capture_does_not_create_the_enrollment(): void
    {
        [$student, $course, $order] = $this->pendingCourseOrder();

        $this->fakeGatewayResponse($order, ['amount' => 1]);

        $this->confirm($student, $order);

        $this->assertDatabaseMissing('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_a_short_capture_is_rejected_even_when_close_to_the_price(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        // One cent short — no tolerance, no rounding slack.
        $this->fakeGatewayResponse($order, ['amount' => 49_999]);

        $this->confirm($student, $order);

        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_an_overpayment_is_also_rejected(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        // Not "safe because we got more" — a mismatch means the captured
        // transaction is not the one this order describes.
        $this->fakeGatewayResponse($order, ['amount' => 60_000]);

        $this->confirm($student, $order);

        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_a_missing_amount_field_fails_closed(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        // Note: only ONE Http::fake here. Registering a second stub for the
        // same URL does not replace the first — the earliest match wins — so a
        // leftover full-response fake would mask this scenario entirely.
        Http::fake([
            self::CONFIRM_URL => Http::response([
                'statusCode'          => 3,
                'transactionStatus'   => 'Approved',
                'clientTransactionId' => $order->client_transaction_id,
                'currency'            => 'USD',
            ]),
        ]);

        $this->confirm($student, $order);

        // If the field ever disappears from the provider's contract, refuse —
        // never fall back to trusting statusCode alone.
        $this->assertSame('failed', $order->fresh()->status);
    }

    // =========================================================================
    // Currency and transaction identity
    // =========================================================================

    public function test_a_capture_in_another_currency_is_rejected(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        // 50000 of a weaker unit is not 50000 USD.
        $this->fakeGatewayResponse($order, ['currency' => 'COP']);

        $this->confirm($student, $order);

        $this->assertSame('failed', $order->fresh()->status);
    }

    public function test_a_capture_belonging_to_another_order_is_rejected(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        // Replaying an approved transaction from a different (cheap) order.
        $this->fakeGatewayResponse($order, [
            'clientTransactionId' => 'ORD-some-other-cheap-order',
        ]);

        $this->confirm($student, $order);

        $this->assertSame('failed', $order->fresh()->status);
    }

    // =========================================================================
    // The legitimate path still works
    // =========================================================================

    public function test_a_matching_capture_pays_the_order_and_enrolls_the_student(): void
    {
        [$student, $course, $order] = $this->pendingCourseOrder();

        $this->fakeGatewayResponse($order);

        $this->confirm($student, $order)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.enrolled', true);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseHas('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_a_declined_capture_still_fails_the_order(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        $this->fakeGatewayResponse($order, [
            'statusCode'        => 2,
            'transactionStatus' => 'Canceled',
        ]);

        $this->confirm($student, $order);

        $this->assertSame('failed', $order->fresh()->status);
        $this->assertSame(0, Enrollment::count());
    }

    // =========================================================================
    // The clientTxId sent to the gateway comes from the ORDER, not the request
    // =========================================================================

    public function test_the_gateway_is_queried_with_the_orders_own_transaction_id(): void
    {
        [$student, , $order] = $this->pendingCourseOrder();

        $this->fakeGatewayResponse($order);

        $this->confirm($student, $order);

        Http::assertSent(fn ($request) => $request->data()['clientTxId'] === $order->client_transaction_id);
    }
}
