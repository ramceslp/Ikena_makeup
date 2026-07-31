<?php

namespace Tests\Feature\Checkout;

use App\Models\CheckoutHandoff;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CourseCheckoutHandoffTest — the `course` shape of the mobile-app -> web
 * checkout handoff (POST /api/checkout/handoff, POST /api/checkout/handoff/redeem).
 *
 * Sibling of CheckoutHandoffControllerTest, which covers the product_cart and
 * appointment shapes. Split into its own file because the course path also
 * exercises CourseCheckoutAction — the extraction of
 * CheckoutController::checkout() that made a course handoff possible at all —
 * including its two failure modes (unavailable -> 422, already enrolled -> 409)
 * and the claim-release behaviour they trigger.
 */
class CourseCheckoutHandoffTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCourse(array $attrs = []): Course
    {
        $instructor = User::factory()->instructor()->create();

        return Course::factory()->create(array_merge([
            'instructor_id' => $instructor->id,
            'price' => 49.99,
            'is_published' => true,
        ], $attrs));
    }

    private function coursePayload(Course $course): array
    {
        return ['type' => 'course', 'course_id' => $course->id];
    }

    /** @return array{0: string, 1: CheckoutHandoff} */
    private function seedHandoff(User $user, array $payload, array $overrides = []): array
    {
        $plaintext = 'plaintext-token-'.str()->random(32);

        $handoff = CheckoutHandoff::create(array_merge([
            'user_id' => $user->id,
            'type' => 'course',
            'token_hash' => hash('sha256', $plaintext),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(10),
        ], $overrides));

        return [$plaintext, $handoff];
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff — store
    // -------------------------------------------------------------------------

    public function test_store_course_shape_returns_201_with_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $course = $this->makeCourse();

        $response = $this->postJson('/api/checkout/handoff', $this->coursePayload($course));

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);

        $this->assertStringContainsString('/checkout/resume#token=', $response->json('data.url'));

        $handoff = CheckoutHandoff::first();
        $this->assertSame('course', $handoff->type);
        $this->assertSame($course->id, $handoff->payload['course_id']);
        $this->assertNull($handoff->consumed_at);
    }

    public function test_store_course_shape_requires_authentication(): void
    {
        $course = $this->makeCourse();

        $this->postJson('/api/checkout/handoff', $this->coursePayload($course))
            ->assertStatus(401);
    }

    public function test_store_course_shape_rejects_missing_course_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/checkout/handoff', ['type' => 'course'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_id');
    }

    public function test_store_course_shape_rejects_unknown_course_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/checkout/handoff', ['type' => 'course', 'course_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('course_id');
    }

    /**
     * The snapshot endpoint deliberately does NOT run business rules — it only
     * writes a snapshot. Eligibility is re-checked at redeem, when it is
     * actually authoritative (see StoreCheckoutHandoffRequest's docblock).
     */
    public function test_store_does_not_reject_an_already_enrolled_course(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $course = $this->makeCourse();
        Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'price_paid' => 49.99]);

        $this->postJson('/api/checkout/handoff', $this->coursePayload($course))
            ->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff/redeem — course branch
    // -------------------------------------------------------------------------

    public function test_redeem_course_creates_pending_order_and_returns_confirm_token(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse(['price' => 49.99]);
        [$token] = $this->seedHandoff($user, ['course_id' => $course->id]);

        $response = $this->postJson('/api/checkout/handoff/redeem', ['token' => $token]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['order_id', 'provider', 'config', 'confirm_token']]);

        $order = Order::find($response->json('data.order_id'));
        $this->assertSame('course', $order->type);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($course->id, $order->course_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame(4999, $order->amount_cents);

        // No Enrollment yet — that is CheckoutController::confirm()'s job,
        // after the gateway approves.
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_redeem_course_consumes_the_token_single_use(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        [$token, $handoff] = $this->seedHandoff($user, ['course_id' => $course->id]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])->assertStatus(201);
        $this->assertNotNull($handoff->fresh()->consumed_at);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])->assertStatus(409);
    }

    public function test_redeem_course_returns_409_when_already_enrolled_and_releases_the_claim(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'price_paid' => 49.99]);
        [$token, $handoff] = $this->seedHandoff($user, ['course_id' => $course->id]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
        // Claim released so the customer can retry the same link.
        $this->assertNull($handoff->fresh()->consumed_at);
    }

    public function test_redeem_course_returns_422_when_the_course_was_unpublished_after_handoff(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        [$token, $handoff] = $this->seedHandoff($user, ['course_id' => $course->id]);

        $course->update(['is_published' => false]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
        $this->assertNull($handoff->fresh()->consumed_at);
    }

    public function test_redeem_course_returns_422_when_the_course_became_free_after_handoff(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        [$token] = $this->seedHandoff($user, ['course_id' => $course->id]);

        $course->update(['price' => 0]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * A course deleted during the token's 10-minute window is an ordinary
     * catalog change, not a server fault — it must not surface as a 500.
     */
    public function test_redeem_course_returns_422_when_the_course_was_deleted_after_handoff(): void
    {
        $user = User::factory()->create();
        $course = $this->makeCourse();
        [$token] = $this->seedHandoff($user, ['course_id' => $course->id]);

        $course->delete();

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])
            ->assertStatus(422);
    }

    public function test_redeem_course_binds_the_order_to_the_handoff_user_not_the_anonymous_caller(): void
    {
        $owner = User::factory()->create();
        $someoneElse = User::factory()->create();
        Sanctum::actingAs($someoneElse);

        $course = $this->makeCourse();
        [$token] = $this->seedHandoff($owner, ['course_id' => $course->id]);

        $response = $this->postJson('/api/checkout/handoff/redeem', ['token' => $token])
            ->assertStatus(201);

        $this->assertSame($owner->id, Order::find($response->json('data.order_id'))->user_id);
    }

    // -------------------------------------------------------------------------
    // Direct POST /api/courses/{slug}/checkout — behaviour preserved after the
    // CourseCheckoutAction extraction.
    // -------------------------------------------------------------------------

    public function test_direct_checkout_rejects_an_unpublished_course(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $course = $this->makeCourse(['is_published' => false]);

        $this->postJson("/api/courses/{$course->slug}/checkout")
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }
}
