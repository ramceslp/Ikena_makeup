<?php

namespace Tests\Feature\Checkout;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\CheckoutHandoff;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CheckoutHandoffControllerTest
 *
 * Tests POST /api/checkout/handoff (auth) and POST /api/checkout/handoff/redeem
 * (public) — mobile-capacitor-setup PR2, design Decision 1
 * (sdd/mobile-capacitor-setup/design.md), spec "Checkout-Handoff Endpoint"
 * (sdd/mobile-capacitor-setup/spec.md).
 *
 * Tasks 2.3-2.10 (RED -> GREEN).
 */
class CheckoutHandoffControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeProduct(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 100.00,
            'stock_qty' => 10,
            'is_published' => true,
        ], $attrs));
    }

    private function makeService(float $price = 100.00, int $depositPct = 30, int $durationHours = 1): Service
    {
        return Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => $price,
            'deposit_percentage' => $depositPct,
            'duration_hours' => $durationHours,
        ]);
    }

    private function makeBlockForToday(array $overrides = []): AgendaBlock
    {
        return AgendaBlock::factory()->create(array_merge([
            'day_of_week' => Carbon::today()->dayOfWeek,
            'specific_date' => null,
            'open_time' => '09:00',
            'close_time' => '18:00',
            'concurrency_limit' => 2,
            'soft_threshold' => null,
            'is_blocked' => false,
        ], $overrides));
    }

    /** @return array{0: Service, 1: AgendaBlock} */
    private function makeBookableService(array $blockOverrides = []): array
    {
        $service = $this->makeService();
        $block = $this->makeBlockForToday($blockOverrides);

        return [$service, $block];
    }

    private function makeExistingAppointment(
        string $date,
        string $startTime,
        string $endTime,
        string $status = 'pending'
    ): Appointment {
        return Appointment::factory()->create([
            'scheduled_date' => $date,
            'scheduled_time' => $startTime,
            'scheduled_end_time' => $endTime,
            'status' => $status,
            'slot_key' => null,
        ]);
    }

    private function today(): string
    {
        return Carbon::today()->format('Y-m-d');
    }

    private function cartPayload(Product $product, int $qty = 1): array
    {
        return [
            'type' => 'product_cart',
            'items' => [
                ['product_id' => $product->id, 'quantity' => $qty],
            ],
        ];
    }

    private function appointmentPayload(Service $service, string $time = '10:00'): array
    {
        return [
            'type' => 'appointment',
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => $time,
            'whatsapp' => '+593999999999',
        ];
    }

    /**
     * Create a handoff row directly (bypassing the store endpoint) and return
     * [plaintextToken, CheckoutHandoff] — used by redeem-focused tests.
     */
    private function seedHandoff(User $user, string $type, array $payload, array $overrides = []): array
    {
        $plaintext = 'plaintext-token-'.str()->random(32);

        $handoff = CheckoutHandoff::create(array_merge([
            'user_id' => $user->id,
            'type' => $type,
            'token_hash' => hash('sha256', $plaintext),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(10),
        ], $overrides));

        return [$plaintext, $handoff];
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff — store
    // -------------------------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $product = $this->makeProduct();

        $this->postJson('/api/checkout/handoff', $this->cartPayload($product))
            ->assertStatus(401);
    }

    public function test_store_cart_shape_returns_201_with_url_and_expires_at(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $product = $this->makeProduct();

        $response = $this->postJson('/api/checkout/handoff', $this->cartPayload($product, 2));

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);

        $url = $response->json('data.url');
        $this->assertStringContainsString('/checkout/resume#token=', $url);

        // Exactly one row created, hashed token stored (never the plaintext).
        $this->assertDatabaseCount('checkout_handoffs', 1);
        $handoff = CheckoutHandoff::first();
        $this->assertSame($user->id, $handoff->user_id);
        $this->assertSame('product_cart', $handoff->type);
        $this->assertSame(2, $handoff->payload['items'][0]['quantity']);
        $this->assertNotNull($handoff->expires_at);
        $this->assertNull($handoff->consumed_at);

        // The plaintext token from the URL must NOT equal the stored hash.
        [, $fragment] = explode('#token=', $url);
        $this->assertNotSame($fragment, $handoff->token_hash);
        $this->assertSame(hash('sha256', $fragment), $handoff->token_hash);
    }

    public function test_store_appointment_shape_returns_201(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        [$service] = $this->makeBookableService();

        $response = $this->postJson('/api/checkout/handoff', $this->appointmentPayload($service));

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);

        $handoff = CheckoutHandoff::first();
        $this->assertSame('appointment', $handoff->type);
        $this->assertSame($service->id, $handoff->payload['service_id']);
    }

    public function test_store_rejects_empty_cart_with_422(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/checkout/handoff', ['type' => 'product_cart', 'items' => []])
            ->assertStatus(422);
    }

    public function test_store_rejects_invalid_type_with_422(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/checkout/handoff', ['type' => 'not-a-type'])
            ->assertStatus(422);
    }

    public function test_store_does_not_reserve_stock_or_create_order(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $product = $this->makeProduct(['stock_qty' => 5]);

        $this->postJson('/api/checkout/handoff', $this->cartPayload($product, 2))
            ->assertStatus(201);

        // Stock untouched — reservation only happens at redeem.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 5]);
        $this->assertDatabaseCount('orders', 0);
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff/redeem — success (product_cart)
    // -------------------------------------------------------------------------

    public function test_redeem_cart_happy_path_returns_order_provider_config_and_confirm_token(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock_qty' => 10]);
        [$plaintext, $handoff] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response = $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['order_id', 'provider', 'config', 'confirm_token']]);

        // Order created, bound to the handoff's user (not to any request auth).
        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $user->id,
            'type' => 'product_cart',
        ]);

        // Stock actually reserved now (deferred from store to redeem).
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 8]);

        // Handoff consumed.
        $handoff->refresh();
        $this->assertNotNull($handoff->consumed_at);

        // confirm_token authenticates as the bound user and is scoped to
        // the 'checkout-confirm' ability with a ~15-minute expiry.
        $confirmToken = $response->json('data.confirm_token');
        $accessToken = PersonalAccessToken::findToken($confirmToken);

        $this->assertNotNull($accessToken);
        $this->assertSame($user->id, $accessToken->tokenable_id);
        $this->assertTrue($accessToken->can('checkout-confirm'));
        $this->assertFalse($accessToken->can('*'));
        $this->assertNotNull($accessToken->expires_at);
        $this->assertTrue($accessToken->expires_at->between(now()->addMinutes(10), now()->addMinutes(20)));

        // The confirm_token is scoped to checkout-confirm ONLY — it must not
        // authenticate as the full user on unrelated auth:sanctum routes
        // (see RejectScopedCheckoutToken and the dedicated scope tests below).
        $this->withHeader('Authorization', "Bearer {$confirmToken}")
            ->getJson('/api/me')
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff/redeem — success (appointment)
    // -------------------------------------------------------------------------

    public function test_redeem_appointment_happy_path_returns_order_provider_config_and_confirm_token(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService();

        [$plaintext, $handoff] = $this->seedHandoff($user, 'appointment', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593999999999',
        ]);

        $response = $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['order_id', 'provider', 'config', 'confirm_token']]);

        $orderId = $response->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $user->id,
            'type' => 'appointment',
        ]);
        $this->assertDatabaseHas('appointments', [
            'order_id' => $orderId,
            'user_id' => $user->id,
            'service_id' => $service->id,
        ]);

        $handoff->refresh();
        $this->assertNotNull($handoff->consumed_at);
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff/redeem — errors
    // -------------------------------------------------------------------------

    public function test_redeem_unknown_token_returns_404(): void
    {
        $this->postJson('/api/checkout/handoff/redeem', ['token' => 'this-token-does-not-exist'])
            ->assertStatus(404);
    }

    public function test_redeem_expired_token_returns_410(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();

        [$plaintext] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], ['expires_at' => now()->subMinute()]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(410);

        // No order created, stock untouched.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 10]);
    }

    public function test_redeem_already_consumed_token_returns_409(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();

        [$plaintext] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], ['consumed_at' => now()->subMinute()]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_redeem_same_token_twice_second_call_returns_409(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock_qty' => 10]);

        [$plaintext] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(201);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(409);

        // Only one order created despite two redeem attempts.
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_redeem_out_of_stock_propagates_409_and_does_not_consume_creates_no_order(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock_qty' => 1]);

        [$plaintext, $handoff] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_qty' => 1]);

        // Fix #3 — the atomic claim must be released on business-exception
        // failure so the customer's link is not permanently dead.
        $handoff->refresh();
        $this->assertNull($handoff->consumed_at);
    }

    public function test_redeem_after_transient_out_of_stock_failure_can_be_retried_with_same_token(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock_qty' => 1]);

        [$plaintext, $handoff] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(409);
        $this->assertDatabaseCount('orders', 0);

        // Stock replenished (e.g. restocked) — retrying the SAME link must
        // now succeed instead of being permanently dead.
        $product->update(['stock_qty' => 10]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(201);

        $this->assertDatabaseCount('orders', 1);
        $handoff->refresh();
        $this->assertNotNull($handoff->consumed_at);
    }

    public function test_redeem_unavailable_product_propagates_422(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct();
        $productId = $product->id;
        $product->delete();

        [$plaintext, $handoff] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(422);

        $handoff->refresh();
        $this->assertNull($handoff->consumed_at);
    }

    public function test_redeem_appointment_capacity_exceeded_propagates_409(): void
    {
        $user = $this->makeUser();
        [$service, $block] = $this->makeBookableService(['concurrency_limit' => 1]);

        // Fill the only slot with an existing overlapping appointment.
        $this->makeExistingAppointment($this->today(), '10:00', '11:00');

        [$plaintext, $handoff] = $this->seedHandoff($user, 'appointment', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593999999999',
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 0);

        $handoff->refresh();
        $this->assertNull($handoff->consumed_at);
    }

    public function test_redeem_unexpected_exception_returns_500_logs_and_releases_claim(): void
    {
        $user = $this->makeUser();

        // service_id references a Service row that doesn't exist — mirrors an
        // unexpected failure (e.g. a deleted Service) hitting Service::findOrFail()
        // inside CreateBookingAction, which is NOT one of the typed business
        // exceptions this controller otherwise catches.
        [$plaintext, $handoff] = $this->seedHandoff($user, 'appointment', [
            'service_id' => 999999,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593999999999',
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(500);

        $this->assertDatabaseCount('orders', 0);
        $handoff->refresh();
        $this->assertNull($handoff->consumed_at);
    }

    // -------------------------------------------------------------------------
    // POST /api/checkout/handoff/redeem — business-rule re-validation (fix #2)
    // -------------------------------------------------------------------------

    public function test_redeem_appointment_unpublished_service_returns_422(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService();
        $service->update(['is_published' => false]);

        [$plaintext] = $this->seedHandoff($user, 'appointment', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593999999999',
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_redeem_appointment_non_by_appointment_service_returns_422(): void
    {
        $user = $this->makeUser();
        [$service] = $this->makeBookableService();
        $service->update(['availability_type' => 'walk_in']);

        [$plaintext] = $this->seedHandoff($user, 'appointment', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '10:00',
            'whatsapp' => '+593999999999',
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_redeem_appointment_duration_overflow_returns_422(): void
    {
        $user = $this->makeUser();
        // 2-hour service, block closes at 18:00 — a 17:00 start overflows to 19:00.
        $service = $this->makeService(durationHours: 2);
        $this->makeBlockForToday(['open_time' => '09:00', 'close_time' => '18:00']);

        [$plaintext] = $this->seedHandoff($user, 'appointment', [
            'service_id' => $service->id,
            'scheduled_date' => $this->today(),
            'scheduled_time' => '17:00',
            'whatsapp' => '+593999999999',
        ]);

        $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    // -------------------------------------------------------------------------
    // confirm_token scope — RejectScopedCheckoutToken middleware (fix #1)
    // -------------------------------------------------------------------------

    private function redeemAndGetConfirmToken(): string
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock_qty' => 10]);
        [$plaintext] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response = $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext]);
        $response->assertStatus(201);

        return $response->json('data.confirm_token');
    }

    public function test_confirm_token_is_rejected_by_me_route(): void
    {
        $confirmToken = $this->redeemAndGetConfirmToken();

        $this->withHeader('Authorization', "Bearer {$confirmToken}")
            ->getJson('/api/me')
            ->assertStatus(403)
            ->assertJson(['message' => 'This token cannot be used for this action.']);
    }

    public function test_confirm_token_is_rejected_by_profile_route(): void
    {
        $confirmToken = $this->redeemAndGetConfirmToken();

        $this->withHeader('Authorization', "Bearer {$confirmToken}")
            ->postJson('/api/profile', [])
            ->assertStatus(403)
            ->assertJson(['message' => 'This token cannot be used for this action.']);
    }

    public function test_confirm_token_is_rejected_by_cart_checkout_route(): void
    {
        $confirmToken = $this->redeemAndGetConfirmToken();

        $this->withHeader('Authorization', "Bearer {$confirmToken}")
            ->postJson('/api/cart/checkout', [])
            ->assertStatus(403)
            ->assertJson(['message' => 'This token cannot be used for this action.']);
    }

    public function test_confirm_token_still_succeeds_against_payments_confirm(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct(['stock_qty' => 10]);
        [$plaintext] = $this->seedHandoff($user, 'product_cart', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $redeemResponse = $this->postJson('/api/checkout/handoff/redeem', ['token' => $plaintext]);
        $redeemResponse->assertStatus(201);

        $confirmToken = $redeemResponse->json('data.confirm_token');
        $orderId = $redeemResponse->json('data.order_id');
        $order = Order::find($orderId);

        $this->withHeader('Authorization', "Bearer {$confirmToken}")
            ->postJson('/api/payments/confirm', [
                'id' => 1,
                'clientTransactionId' => $order->client_transaction_id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_normal_full_access_token_still_works_on_protected_routes(): void
    {
        $user = $this->makeUser();
        $normalToken = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$normalToken}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
