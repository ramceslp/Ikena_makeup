<?php

namespace App\Http\Controllers\Api;

use App\Actions\CartCheckoutAction;
use App\Actions\CreateBookingAction;
use App\Exceptions\BookingCapacityExceededException;
use App\Exceptions\BookingSlotUnavailableException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\ProductUnavailableException;
use App\Exceptions\ServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RedeemCheckoutHandoffRequest;
use App\Http\Requests\StoreCheckoutHandoffRequest;
use App\Models\CheckoutHandoff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CheckoutHandoffController — mobile-app -> web checkout handoff
 * (mobile-capacitor-setup PR2, design Decision 1).
 *
 * store()  (auth:sanctum) snapshots the authenticated user's cart/booking
 *          selection behind an opaque single-use token and returns a web
 *          resume URL. It does NOT reserve stock or create an Order — that
 *          happens at redeem, matching current timing where reservation
 *          occurs when checkout actually starts.
 * redeem() (public)        hashes the presented token, atomically claims the
 *          matching row (single-use), then runs the exact same
 *          CartCheckoutAction / CreateBookingAction used by the direct
 *          checkout endpoints — as the handoff's bound user, never the
 *          (anonymous) redeeming request — so money/stock/capacity rules
 *          stay single-sourced. Returns a short-lived, ability-scoped
 *          Sanctum token so the anonymous web browser can complete
 *          POST /api/payments/confirm without a full login.
 */
class CheckoutHandoffController extends Controller
{
    public function __construct(
        private readonly CartCheckoutAction $cartCheckout,
        private readonly CreateBookingAction $createBooking,
    ) {}

    public function store(StoreCheckoutHandoffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'];
        $user = $request->user();

        $payload = $type === 'appointment'
            ? Arr::only($validated, ['service_id', 'scheduled_date', 'scheduled_time', 'whatsapp'])
            : ['items' => $validated['items']];

        $plaintextToken = Str::random(40);

        $handoff = CheckoutHandoff::create([
            'user_id' => $user->id,
            'type' => $type,
            'token_hash' => hash('sha256', $plaintextToken),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(10),
        ]);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return response()->json([
            'data' => [
                'url' => "{$frontendUrl}/checkout/resume#token={$plaintextToken}",
                'expires_at' => $handoff->expires_at->toJSON(),
            ],
        ], 201);
    }

    public function redeem(RedeemCheckoutHandoffRequest $request): JsonResponse
    {
        $tokenHash = hash('sha256', $request->validated()['token']);

        $handoff = CheckoutHandoff::where('token_hash', $tokenHash)->first();

        if (! $handoff) {
            return response()->json(['message' => 'Unknown or invalid checkout link.'], 404);
        }

        if ($handoff->consumed_at !== null) {
            return response()->json(['message' => 'This checkout link has already been used.'], 409);
        }

        if ($handoff->expires_at->isPast()) {
            return response()->json([
                'message' => 'This checkout link has expired. Please restart checkout from the app.',
            ], 410);
        }

        // Atomic single-use claim — the real concurrency guard. The checks
        // above are a fast-fail UX path; even if two redeem requests race
        // past them, only one of these guarded UPDATEs can affect a row.
        $claimed = DB::table('checkout_handoffs')
            ->where('id', $handoff->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return response()->json(['message' => 'This checkout link has already been used.'], 409);
        }

        $user = User::findOrFail($handoff->user_id);
        $payload = $handoff->payload;

        try {
            $result = $handoff->type === 'appointment'
                ? ($this->createBooking)(
                    $user,
                    (int) $payload['service_id'],
                    $payload['scheduled_date'],
                    $payload['scheduled_time'],
                    $payload['whatsapp'] ?? null,
                )
                : ($this->cartCheckout)($user, $payload['items']);
        } catch (ProductUnavailableException $e) {
            return response()->json([
                'message' => 'One or more products are unavailable or unpublished.',
                'product_id' => $e->productId,
            ], 422);
        } catch (OutOfStockException $e) {
            return response()->json([
                'message' => 'Insufficient stock for one or more items.',
                'product_id' => $e->productId,
            ], 409);
        } catch (ServiceUnavailableException $e) {
            // Mirrors StoreBookingRequest::withValidator()'s 422 for the same
            // business-rule failures, re-validated inside CreateBookingAction
            // because this snapshot may be up to 10 minutes stale.
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (BookingSlotUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (BookingCapacityExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'cap_exceeded',
            ], 409);
        }

        // Short-lived, ability-scoped token — lets the anonymous web browser
        // complete /payments/confirm as the bound user without a full login.
        $confirmToken = $user->createToken(
            'checkout-confirm',
            ['checkout-confirm'],
            now()->addMinutes(15),
        )->plainTextToken;

        return response()->json([
            'data' => [
                'order_id' => $result['order']->id,
                'provider' => $result['session']->provider,
                'config' => $result['session']->config,
                'confirm_token' => $confirmToken,
            ],
        ], 201);
    }
}
