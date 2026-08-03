<?php

namespace Tests\Feature\Analytics;

use App\Models\Product;
use App\Models\User;
use App\Models\VisitorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * VisitorEventIngestionTest — full derivation behavior of
 * VisitorEventController::store() (visitor-analytics PR1b, design D4/D6/D9:
 * sdd/visitor-analytics/design).
 *
 * Every derived field — is_bot, referrer_group, entity_id, occurred_at,
 * user_id — MUST be computed server-side and MUST NOT be taken from the
 * request body. Raw IP and raw User-Agent must appear in no stored column
 * (design D1: no visitor identity of any kind). A 401 must never be
 * returned from this endpoint under any circumstance.
 */
class VisitorEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_event_is_recorded_with_no_user_attribution(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/',
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertNull($event->user_id);
    }

    public function test_a_bearer_token_stamps_the_event_with_the_user_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/',
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertSame($user->id, $event->user_id);
    }

    public function test_a_401_is_never_returned_even_with_a_garbage_bearer_token(): void
    {
        $this->withHeader('Authorization', 'Bearer this-is-not-a-real-token')
            ->postJson('/api/analytics/events', [
                'event_type' => 'page_view',
                'path' => '/',
            ])->assertStatus(204);
    }

    public function test_a_client_supplied_occurred_at_is_ignored_in_favor_of_the_server_clock(): void
    {
        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/',
            'occurred_at' => '2020-01-01 00:00:00',
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertTrue(
            $event->occurred_at->greaterThan(now()->subMinute()),
            'occurred_at must reflect the server clock, not a client-supplied value.'
        );
    }

    public function test_client_supplied_is_bot_referrer_group_and_entity_id_are_ignored(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/analytics/events', [
            'event_type' => 'page_view',
            'path' => '/productos/'.$product->slug,
            'entity_type' => 'product',
            'entity_slug' => $product->slug,
            'referrer' => 'https://www.instagram.com/some-post/',
            // Hostile extras — none of these are validated fields, and
            // even if they were, the server must never trust them.
            'is_bot' => true,
            'referrer_group' => 'google',
            'entity_id' => 999999,
        ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertFalse($event->is_bot, 'is_bot must be server-derived, never client-trusted.');
        $this->assertSame('instagram', $event->referrer_group, 'referrer_group must be derived from the real Referer, not the body.');
        $this->assertSame($product->id, $event->entity_id, 'entity_id must be resolved server-side from the slug, not taken from the body.');
    }

    public function test_a_known_bot_user_agent_is_flagged(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->postJson('/api/analytics/events', [
                'event_type' => 'page_view',
                'path' => '/',
            ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();

        $this->assertTrue($event->is_bot);
    }

    public function test_raw_ip_and_raw_user_agent_appear_in_no_stored_column(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TestBrowser/1.0';

        $this->withHeader('User-Agent', $userAgent)
            ->postJson('/api/analytics/events', [
                'event_type' => 'page_view',
                'path' => '/',
            ])->assertStatus(204);

        $event = VisitorEvent::query()->firstOrFail();
        $attributes = $event->getAttributes();

        foreach (array_keys($attributes) as $column) {
            $this->assertStringNotContainsStringIgnoringCase('_ip', $column);
            $this->assertStringNotContainsStringIgnoringCase('agent', $column);
        }

        $this->assertNotContains($userAgent, $attributes, 'The raw User-Agent must never be stored verbatim.');
    }
}
