<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function paidCourse(): Course
    {
        $instructor = User::factory()->instructor()->create();

        return Course::factory()->create([
            'instructor_id' => $instructor->id,
            'price'         => 49.99,
            'is_published'  => true,
        ]);
    }

    private function freeCourse(): Course
    {
        $instructor = User::factory()->instructor()->create();

        return Course::factory()->create([
            'instructor_id' => $instructor->id,
            'price'         => 0,
            'is_published'  => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/courses/{slug}/checkout
    // -------------------------------------------------------------------------

    public function test_checkout_requires_authentication(): void
    {
        $course = $this->paidCourse();

        $this->postJson("/api/courses/{$course->slug}/checkout")
             ->assertStatus(401);
    }

    public function test_checkout_returns_422_for_free_course(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->freeCourse();

        $this->postJson("/api/courses/{$course->slug}/checkout")
             ->assertStatus(422);
    }

    public function test_checkout_returns_409_if_already_enrolled(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->paidCourse();

        Enrollment::create([
            'user_id'    => $student->id,
            'course_id'  => $course->id,
            'price_paid' => $course->price,
        ]);

        $this->postJson("/api/courses/{$course->slug}/checkout")
             ->assertStatus(409);
    }

    public function test_checkout_creates_pending_order_and_returns_config(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->paidCourse();

        $response = $this->postJson("/api/courses/{$course->slug}/checkout");

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['order_id', 'provider', 'config'],
                 ]);

        // A pending order must exist in the database.
        $this->assertDatabaseHas('orders', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
            'status'    => 'pending',
        ]);

        // The config must have the expected keys.
        $config = $response->json('data.config');
        $this->assertArrayHasKey('clientTransactionId', $config);
        $this->assertArrayHasKey('amount', $config);
    }

    // -------------------------------------------------------------------------
    // POST /api/payments/confirm — approved flow
    // -------------------------------------------------------------------------

    public function test_confirm_approved_marks_order_paid_and_creates_enrollment(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->paidCourse();

        // Step 1: create the order via checkout.
        $checkoutResponse = $this->postJson("/api/courses/{$course->slug}/checkout");
        $checkoutResponse->assertStatus(201);

        $orderId             = $checkoutResponse->json('data.order_id');
        $clientTransactionId = $checkoutResponse->json('data.config.clientTransactionId');

        // Step 2: confirm — clientTransactionId does NOT contain "decline" => approved.
        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 9999,
            'clientTransactionId' => $clientTransactionId,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'paid')
                 ->assertJsonPath('data.enrolled', true)
                 ->assertJsonPath('data.course_slug', $course->slug);

        // Order must be marked paid.
        $this->assertDatabaseHas('orders', [
            'id'     => $orderId,
            'status' => 'paid',
        ]);

        // Enrollment must exist.
        $this->assertDatabaseHas('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_confirmed_enrollment_appears_in_my_courses(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course  = $this->paidCourse();
        $section = Section::factory()->create(['course_id' => $course->id]);

        $checkoutResponse = $this->postJson("/api/courses/{$course->slug}/checkout");
        $clientTransactionId = $checkoutResponse->json('data.config.clientTransactionId');

        $this->postJson('/api/payments/confirm', [
            'id'                  => 9999,
            'clientTransactionId' => $clientTransactionId,
        ])->assertStatus(200);

        $myCoursesResponse = $this->getJson('/api/my-courses');
        $myCoursesResponse->assertStatus(200);

        $slugs = collect($myCoursesResponse->json('data'))->pluck('slug')->toArray();
        $this->assertContains($course->slug, $slugs);
    }

    // -------------------------------------------------------------------------
    // POST /api/payments/confirm — idempotent (double-confirm)
    // -------------------------------------------------------------------------

    public function test_confirm_twice_is_idempotent_no_duplicate_enrollment(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->paidCourse();

        $checkoutResponse    = $this->postJson("/api/courses/{$course->slug}/checkout");
        $clientTransactionId = $checkoutResponse->json('data.config.clientTransactionId');

        $payload = ['id' => 9999, 'clientTransactionId' => $clientTransactionId];

        // First confirm.
        $this->postJson('/api/payments/confirm', $payload)->assertStatus(200);

        // Second confirm — must return enrolled:true without error or duplicate enrollment.
        $second = $this->postJson('/api/payments/confirm', $payload);
        $second->assertStatus(200)
               ->assertJsonPath('data.status', 'paid')
               ->assertJsonPath('data.enrolled', true);

        // Exactly one enrollment row.
        $this->assertEquals(
            1,
            Enrollment::where('user_id', $student->id)
                       ->where('course_id', $course->id)
                       ->count()
        );
    }

    // -------------------------------------------------------------------------
    // POST /api/payments/confirm — declined flow
    // -------------------------------------------------------------------------

    public function test_confirm_with_decline_clientTransactionId_fails_no_enrollment(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $course = $this->paidCourse();

        // Create order manually with a "decline" client_transaction_id.
        $order = Order::create([
            'user_id'               => $student->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'decline-test-id-1234',
            'gateway'               => 'fake',
            'amount_cents'          => 4999,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        $response = $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'failed')
                 ->assertJsonPath('data.enrolled', false);

        // Order is marked failed.
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'failed',
        ]);

        // No enrollment created.
        $this->assertDatabaseMissing('enrollments', [
            'user_id'   => $student->id,
            'course_id' => $course->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/payments/confirm — security
    // -------------------------------------------------------------------------

    public function test_confirm_returns_403_if_order_belongs_to_another_user(): void
    {
        $owner   = User::factory()->create();
        $intruder = User::factory()->create();

        $course = $this->paidCourse();

        $order = Order::create([
            'user_id'               => $owner->id,
            'course_id'             => $course->id,
            'client_transaction_id' => 'ORD-owner-only-99',
            'gateway'               => 'fake',
            'amount_cents'          => 4999,
            'currency'              => 'USD',
            'status'                => 'pending',
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/payments/confirm', [
            'id'                  => 1,
            'clientTransactionId' => $order->client_transaction_id,
        ])->assertStatus(403);
    }
}
