<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use App\Services\Payments\DTOs\PaymentResult;
use Illuminate\Support\Facades\Http;

class PayPhoneGateway implements PaymentGatewayInterface
{
    public function createCheckout(Order $order): CheckoutSession
    {
        $order->loadMissing('course');

        // Build the exact PPaymentButtonBox config as specified in PAYMENTS.md §5.
        // Constraint: amount = amountWithoutTax + amountWithTax + tax + service + tip
        // With no tax in MVP: amountWithoutTax = amount, rest = 0.
        $config = [
            'token'               => config('services.payments.payphone.token'),
            'clientTransactionId' => $order->client_transaction_id,
            'amount'              => $order->amount_cents,       // INTEGER, cents
            'amountWithoutTax'    => $order->amount_cents,
            'amountWithTax'       => 0,
            'tax'                 => 0,
            'service'             => 0,
            'tip'                 => 0,
            'currency'            => 'USD',
            'storeId'             => config('services.payments.payphone.store_id'),
            'reference'           => mb_substr('Curso: ' . $order->course->title, 0, 100),
            'lang'                => 'es',
        ];

        return new CheckoutSession(provider: $this->name(), config: $config);
    }

    public function confirm(string $gatewayId, string $clientTransactionId): PaymentResult
    {
        $url   = config('services.payments.payphone.confirm_url');
        $token = config('services.payments.payphone.token');

        $response = Http::withToken($token)
            ->post($url, [
                'id'       => (int) $gatewayId,
                'clientTxId' => $clientTransactionId,
            ]);

        $raw      = $response->json() ?? [];
        $approved = ($raw['statusCode'] ?? null) === 3;
        $status   = $approved ? 'paid' : 'failed';

        return new PaymentResult(
            approved: $approved,
            gatewayId: $gatewayId,
            status: $status,
            raw: $raw,
        );
    }

    public function name(): string
    {
        return 'payphone';
    }
}
