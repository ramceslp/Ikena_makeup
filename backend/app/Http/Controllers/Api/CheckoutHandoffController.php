<?php

namespace App\Http\Controllers\Api;

use App\Actions\CartCheckoutAction;
use App\Actions\CourseCheckoutAction;
use App\Actions\CreateBookingAction;
use App\Exceptions\AlreadyEnrolledException;
use App\Exceptions\BookingCapacityExceededException;
use App\Exceptions\BookingSlotUnavailableException;
use App\Exceptions\CourseUnavailableException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\ProductUnavailableException;
use App\Exceptions\ServiceUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RedeemCheckoutHandoffRequest;
use App\Http\Requests\StoreCheckoutHandoffRequest;
use App\Models\CheckoutHandoff;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 *
 * Every redeem() failure carries a stable `code` alongside its English
 * `message`. The redeeming page is a logged-out browser session that shows
 * Spanish copy to a customer, and HTTP status alone is ambiguous: a 409 can
 * mean "this link was already used" (link_consumed), "someone took the last
 * slot" (slot_unavailable / cap_exceeded), or "you already own this course"
 * (already_enrolled) — three completely different things to tell the user.
 * Before the app could hand off anything other than a cart, every 409 was
 * rendered as "el enlace ya fue utilizado", which is simply wrong for the
 * other two. Clients switch on `code`; `message` stays the diagnostic
 * fallback.
 *
 * Codes: link_invalid, link_consumed, link_expired, product_unavailable,
 * out_of_stock, service_unavailable, slot_unavailable, cap_exceeded,
 * course_unavailable, already_enrolled, checkout_failed.
 */
class CheckoutHandoffController extends Controller
{
    public function __construct(
        private readonly CartCheckoutAction $cartCheckout,
        private readonly CreateBookingAction $createBooking,
        private readonly CourseCheckoutAction $courseCheckout,
    ) {}

    public function store(StoreCheckoutHandoffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = $validated['type'];
        $user = $request->user();

        $payload = match ($type) {
            'appointment' => Arr::only($validated, ['service_id', 'scheduled_date', 'scheduled_time', 'whatsapp']),
            'course' => ['course_id' => (int) $validated['course_id']],
            default => ['items' => $validated['items']],
        };

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
        $handoff = CheckoutHandoff::byToken($request->validated()['token'])->first();

        if (! $handoff) {
            return response()->json(['message' => 'Unknown or invalid checkout link.', 'code' => 'link_invalid'], 404);
        }

        if ($handoff->consumed_at !== null) {
            return response()->json(['message' => 'This checkout link has already been used.', 'code' => 'link_consumed'], 409);
        }

        if ($handoff->expires_at->isPast()) {
            return response()->json([
                'message' => 'This checkout link has expired. Please restart checkout from the app.',
                'code' => 'link_expired',
            ], 410);
        }

        // Atomic single-use claim — the real concurrency guard. The checks
        // above are a fast-fail UX path; even if two redeem requests race
        // past them, only one of these guarded UPDATEs can affect a row.
        // The expires_at condition is enforced here too (not just via the
        // earlier read above) so a request arriving in the last instants
        // before expiry can't slip through and claim a technically-expired token.
        $claimed = DB::table('checkout_handoffs')
            ->where('id', $handoff->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            // The atomic UPDATE affected 0 rows, but that doesn't tell us WHY:
            // it could be a prior consumption (true 409) or the token expiring
            // in the narrow gap between the read-time expiry check above and
            // this UPDATE (true 410). Re-read the row's current state (a plain
            // SELECT, not another atomic claim attempt) to disambiguate.
            $current = DB::table('checkout_handoffs')->where('id', $handoff->id)->first();

            if ($current && $current->consumed_at === null && now()->greaterThan($current->expires_at)) {
                return response()->json([
                    'message' => 'This checkout link has expired. Please restart checkout from the app.',
                    'code' => 'link_expired',
                ], 410);
            }

            return response()->json(['message' => 'This checkout link has already been used.', 'code' => 'link_consumed'], 409);
        }

        $payload = $handoff->payload;

        try {
            $user = User::findOrFail($handoff->user_id);

            $result = match ($handoff->type) {
                'appointment' => ($this->createBooking)(
                    $user,
                    (int) $payload['service_id'],
                    $payload['scheduled_date'],
                    $payload['scheduled_time'],
                    $payload['whatsapp'] ?? null,
                ),
                'course' => ($this->courseCheckout)($user, $this->resolveCourse($payload)),
                default => ($this->cartCheckout)($user, $payload['items']),
            };
        } catch (CourseUnavailableException $e) {
            // Unpublished, deleted, or repriced-to-free since the snapshot was
            // taken. Transient from the customer's point of view — release the
            // claim so the link isn't burned by a state change they didn't cause.
            $this->releaseClaim($handoff);

            return response()->json(['message' => $e->getMessage(), 'code' => 'course_unavailable'], 422);
        } catch (AlreadyEnrolledException $e) {
            // Reachable even though the app checks `is_enrolled` before creating
            // the handoff: the same course may have been bought on the web
            // during the token's 10-minute window.
            $this->releaseClaim($handoff);

            return response()->json(['message' => $e->getMessage(), 'code' => 'already_enrolled'], 409);
        } catch (ProductUnavailableException $e) {
            // Transient/business failure — release the claim so the customer
            // can retry the same link instead of it being permanently dead.
            $this->releaseClaim($handoff);

            return response()->json([
                'message' => 'One or more products are unavailable or unpublished.',
                'code' => 'product_unavailable',
                'product_id' => $e->productId,
            ], 422);
        } catch (OutOfStockException $e) {
            $this->releaseClaim($handoff);

            return response()->json([
                'message' => 'Insufficient stock for one or more items.',
                'code' => 'out_of_stock',
                'product_id' => $e->productId,
            ], 409);
        } catch (ServiceUnavailableException $e) {
            // Mirrors StoreBookingRequest::withValidator()'s 422 for the same
            // business-rule failures, re-validated inside CreateBookingAction
            // because this snapshot may be up to 10 minutes stale.
            $this->releaseClaim($handoff);

            return response()->json(['message' => $e->getMessage(), 'code' => 'service_unavailable'], 422);
        } catch (BookingSlotUnavailableException $e) {
            $this->releaseClaim($handoff);

            return response()->json(['message' => $e->getMessage(), 'code' => 'slot_unavailable'], 409);
        } catch (BookingCapacityExceededException $e) {
            $this->releaseClaim($handoff);

            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'cap_exceeded',
            ], 409);
        } catch (\Throwable $e) {
            // Unexpected exception (e.g. a deleted Service -> ModelNotFoundException).
            // Mirrors CartCheckoutController::store()'s catch-all: log it and return
            // a generic 500 instead of letting it fall through uncaught/unlogged.
            // The token wasn't the customer's fault either, so release the claim.
            $this->releaseClaim($handoff);

            Log::error('Checkout handoff redeem failed: '.$e->getMessage(), [
                'handoff_id' => $handoff->id,
                'user_id' => $handoff->user_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Checkout failed. Please try again.', 'code' => 'checkout_failed'], 500);
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

    /**
     * Resolve the course a `course` handoff snapshot points at.
     *
     * Uses find() + an explicit CourseUnavailableException rather than
     * findOrFail(), so a course deleted during the token's 10-minute window
     * produces the same customer-facing 422 ("no longer available") as an
     * unpublished one, instead of falling through to redeem()'s catch-all
     * ModelNotFoundException branch and returning an opaque, logged 500 for
     * what is an ordinary catalog change.
     */
    private function resolveCourse(array $payload): Course
    {
        $course = Course::find((int) $payload['course_id']);

        if (! $course) {
            throw new CourseUnavailableException('This course is no longer available.');
        }

        return $course;
    }

    /**
     * Reset a handoff's atomic single-use claim back to unconsumed after the
     * post-claim Action call fails with a business or unexpected exception.
     *
     * The atomic `UPDATE ... WHERE consumed_at IS NULL` claim taken earlier in
     * redeem() is the correct concurrency/double-redeem guard and must stay —
     * but it runs BEFORE the Action call, so a transient failure (e.g. someone
     * else bought the last unit, an unexpected error) would otherwise burn the
     * token permanently, leaving the customer's link dead with no retry path.
     * Releasing the claim here lets them retry the same link. Skipped if the
     * handoff has also expired in the meantime, since redeeming it again would
     * just hit the expiry check.
     *
     * Uses a query-builder UPDATE rather than $handoff->update() because the
     * in-memory $handoff instance was read BEFORE the atomic claim ran (its
     * consumed_at attribute is still null there) — Eloquent's dirty-tracking
     * would otherwise treat "set consumed_at to null" as a no-op and skip the
     * UPDATE entirely, leaving the claim burned in the database.
     */
    private function releaseClaim(CheckoutHandoff $handoff): void
    {
        if (! $handoff->expires_at->isPast()) {
            CheckoutHandoff::whereKey($handoff->id)->update(['consumed_at' => null]);
        }
    }
}
