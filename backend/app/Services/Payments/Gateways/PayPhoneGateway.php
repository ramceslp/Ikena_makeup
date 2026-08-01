<?php

namespace App\Services\Payments\Gateways;

use App\Models\Order;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\DTOs\CheckoutSession;
use App\Services\Payments\DTOs\PaymentResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    /**
     * Confirm a capture with PayPhone and verify it is the capture THIS order
     * requires.
     *
     * `statusCode === 3` only means "some transaction was approved". It does
     * not mean the right amount was taken: PPaymentButtonBox is a client-side
     * widget, so the amount createCheckout() built travelled through the
     * browser before reaching PayPhone. Approving on statusCode alone let a
     * tampered amount pay one cent for a $500 course — the capture is
     * genuinely approved, it is simply not the one the order describes.
     *
     * Response shape per https://docs.payphone.app/cajita-de-pagos:
     *   { statusCode: 3, transactionStatus: "Approved", amount: 315,
     *     currency: "USD", clientTransactionId: "...", transactionId: ... }
     * `amount` is an INTEGER IN CENTS — the same unit as orders.amount_cents,
     * so it compares directly with no conversion.
     *
     * Every check fails closed: a response missing the fields needed to verify
     * is not approved.
     */
    public function confirm(Order $order, string $gatewayId): PaymentResult
    {
        $response = Http::withToken(config('services.payments.payphone.token'))
            ->post(config('services.payments.payphone.confirm_url'), [
                'id' => (int) $gatewayId,
                // From the order, never from caller-supplied input.
                'clientTxId' => $order->client_transaction_id,
            ]);

        $raw = $response->json() ?? [];

        $reject = function (string $status, string $logMessage, array $context = []) use ($gatewayId, $raw, $order): PaymentResult {
            Log::warning($logMessage, array_merge(['order_id' => $order->id, 'gateway_id' => $gatewayId], $context));

            return new PaymentResult(
                approved: false,
                gatewayId: $gatewayId,
                status: $status,
                raw: $raw,
            );
        };

        // 1. Did the provider approve anything at all?
        if (($raw['statusCode'] ?? null) !== 3) {
            return new PaymentResult(
                approved: false,
                gatewayId: $gatewayId,
                status: 'failed',
                raw: $raw,
            );
        }

        // 2. Does the capture belong to THIS order? Guards against presenting
        //    an approved transaction from a different, cheaper order.
        $returnedTxId = $raw['clientTransactionId'] ?? null;

        if (! is_string($returnedTxId) || ! hash_equals($order->client_transaction_id, $returnedTxId)) {
            return $reject('transaction_mismatch', 'Payment confirm rejected: transaction id does not match the order.', [
                'expected' => $order->client_transaction_id,
                'returned' => $returnedTxId,
            ]);
        }

        // 3. THE LOAD-BEARING CHECK — was the required amount actually taken?
        if (! isset($raw['amount']) || ! is_numeric($raw['amount'])) {
            return $reject('amount_unverifiable', 'Payment confirm rejected: response carried no usable amount.');
        }

        $capturedCents = (int) $raw['amount'];

        if ($capturedCents !== (int) $order->amount_cents) {
            // Not a warning — a mismatch here is either provider drift or
            // someone editing the amount before it reached PayPhone.
            Log::critical('Payment amount mismatch — capture does not match the order.', [
                'order_id'       => $order->id,
                'gateway_id'     => $gatewayId,
                'expected_cents' => (int) $order->amount_cents,
                'captured_cents' => $capturedCents,
            ]);

            return new PaymentResult(
                approved: false,
                gatewayId: $gatewayId,
                status: 'amount_mismatch',
                raw: $raw,
            );
        }

        // 4. Same number, same unit. 50000 COP is not 50000 USD.
        $currency = $raw['currency'] ?? null;

        if (! is_string($currency) || strtoupper($currency) !== strtoupper((string) $order->currency)) {
            return $reject('currency_mismatch', 'Payment confirm rejected: captured currency does not match the order.', [
                'expected' => $order->currency,
                'returned' => $currency,
            ]);
        }

        return new PaymentResult(
            approved: true,
            gatewayId: $gatewayId,
            status: 'paid',
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
