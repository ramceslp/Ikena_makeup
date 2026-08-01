<?php

namespace App\Providers;

use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\FakeGateway;
use App\Services\Payments\Gateways\PayPhoneGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function () {
            // No fallback default. FakeGateway approves every transaction
            // without a network call, so an unset or misconfigured
            // PAYMENT_DRIVER must CRASH here rather than silently degrade
            // into "every payment approved, no money captured".
            // Locked by tests/Feature/PaymentDriverGuardTest.php.
            $driver = config('services.payments.driver');

            if (blank($driver)) {
                throw new InvalidArgumentException(
                    'PAYMENT_DRIVER is not set. Supported: payphone, fake.'
                );
            }

            // Belt and braces: even an explicit PAYMENT_DRIVER=fake in a
            // production .env must not be honoured.
            if ($driver === 'fake' && ! $this->app->environment(['local', 'testing'])) {
                throw new InvalidArgumentException(
                    "PAYMENT_DRIVER=fake is forbidden in the [{$this->app->environment()}] environment."
                );
            }

            return match ($driver) {
                'payphone' => new PayPhoneGateway(),
                'fake'     => new FakeGateway(),
                default    => throw new InvalidArgumentException(
                    "Unknown payment driver [{$driver}]. Supported: payphone, fake."
                ),
            };
        });
    }
}
