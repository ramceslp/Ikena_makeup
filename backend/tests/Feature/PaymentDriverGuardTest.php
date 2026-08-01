<?php

namespace Tests\Feature;

use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\PayPhoneGateway;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * PaymentDriverGuardTest — the payment driver must fail CLOSED.
 *
 * FakeGateway approves every transaction without a network call. Reaching it
 * outside local/testing means 100% payment approval with zero money captured,
 * so a missing or misconfigured PAYMENT_DRIVER must crash the container
 * resolution rather than silently degrade into "everything is free".
 */
class PaymentDriverGuardTest extends TestCase
{
    private function resolveGatewayIn(string $env, ?string $driver): PaymentGatewayInterface
    {
        config(['services.payments.driver' => $driver]);
        $this->app['env'] = $env;

        return $this->app->make(PaymentGatewayInterface::class);
    }

    // =========================================================================
    // Fail-closed
    // =========================================================================

    public function test_fake_driver_is_rejected_in_production(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveGatewayIn('production', 'fake');
    }

    public function test_fake_driver_is_rejected_in_staging(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveGatewayIn('staging', 'fake');
    }

    public function test_unset_driver_throws_instead_of_defaulting_to_fake(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveGatewayIn('production', null);
    }

    public function test_empty_driver_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveGatewayIn('production', '');
    }

    public function test_unset_driver_throws_even_in_local(): void
    {
        // An unset driver is a configuration error everywhere — never a
        // silent opt-in to the approve-everything gateway.
        $this->expectException(InvalidArgumentException::class);

        $this->resolveGatewayIn('local', null);
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resolveGatewayIn('production', 'stripe');
    }

    // =========================================================================
    // Legitimate resolutions still work
    // =========================================================================

    public function test_payphone_driver_resolves_in_production(): void
    {
        $this->assertInstanceOf(
            PayPhoneGateway::class,
            $this->resolveGatewayIn('production', 'payphone'),
        );
    }

    public function test_fake_driver_resolves_in_testing(): void
    {
        // phpunit.xml pins PAYMENT_DRIVER=fake — the whole suite depends on
        // the fake gateway remaining reachable under APP_ENV=testing.
        $this->assertInstanceOf(
            FakeGateway::class,
            $this->resolveGatewayIn('testing', 'fake'),
        );
    }

    public function test_fake_driver_resolves_in_local(): void
    {
        $this->assertInstanceOf(
            FakeGateway::class,
            $this->resolveGatewayIn('local', 'fake'),
        );
    }
}
