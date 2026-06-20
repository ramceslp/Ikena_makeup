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
        // Build a type-aware payment reference (no null-deref on non-course orders).
        $reference = match ($order->type) {
            'appointment'  => $this->appointmentReference($order),
            'product_cart' => 'Pedido #' . $order->id,
            default        => $this->courseReference($order), // 'course' and any future types
        };

        // Load the appropriate relation to avoid extra queries, but only when needed.
        match ($order->type) {
            'course'      => $order->loadMissing('course'),
            'appointment' => $order->loadMissing('appointment'),
            default       => null, // product_cart needs no relation
        };

        // Build the exact PPaymentButtonBox config as specified in PAYMENTS.md §5.
        // MVP (PAYMENTS.md §5): no tax breakdown sent to PayPhone for any order type —
        //   amountWithoutTax = amount_cents, amountWithTax/tax/service/tip = 0.
        //   Product IVA is stored in order.tax_cents for accounting only; passing it to
        //   PayPhone is a deferred fiscal/SRI follow-up.
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
            'reference'           => mb_substr($reference, 0, 100),
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
                'id'         => (int) $gatewayId,
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

    // -------------------------------------------------------------------------
    // Private reference builders
    // -------------------------------------------------------------------------

    private function courseReference(Order $order): string
    {
        $order->loadMissing('course');

        return 'Curso: ' . (optional($order->course)->title ?? 'Desconocido');
    }

    private function appointmentReference(Order $order): string
    {
        $order->loadMissing('appointment.service');

        $serviceTitle = optional($order->appointment?->service)->title ?? 'Cita';

        return 'Reserva: ' . $serviceTitle;
    }
}
