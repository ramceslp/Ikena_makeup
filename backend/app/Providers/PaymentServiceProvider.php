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
            $driver = config('services.payments.driver', 'fake');

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
