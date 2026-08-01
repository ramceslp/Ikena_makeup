# Payments Contract — PayPhone (Cajita de Pagos) + Gateway Abstraction

> Architecture rule: the domain (enrollment/orders) MUST NOT depend on PayPhone directly.
> All providers sit behind `PaymentGatewayInterface`. Swapping/adding a provider = a new
> driver, zero changes to controllers or business logic. (Dependency Inversion / SOLID.)

## 1. Domain flow

```
Free course (price = 0):
  POST /api/courses/{slug}/enroll        -> creates Enrollment directly (existing behavior)

Paid course (price > 0):
  POST /api/courses/{slug}/checkout      -> creates Order(pending) + returns gateway box config
  [frontend renders PayPhone Cajita]     -> user pays
  PayPhone redirects to FRONTEND callback ?id=<int>&clientTransactionId=<string>
  POST /api/payments/confirm {id, clientTransactionId}
                                         -> gateway.confirm() -> if approved:
                                            Order=paid + create Enrollment (idempotent)
```

Idempotency: confirming an already-paid order returns the existing enrollment, never
double-charges or double-enrolls. `client_transaction_id` is unique.

## 2. Data model — `orders`

| column                 | type                                   | notes |
|------------------------|----------------------------------------|-------|
| id                     | bigint PK                              | |
| user_id                | FK users cascade                      | |
| course_id              | FK courses cascade                    | |
| client_transaction_id  | string(50) unique                     | our id sent to PayPhone |
| gateway                | string default 'payphone'             | driver used |
| gateway_transaction_id | string nullable                       | PayPhone `id` after confirm |
| amount_cents           | unsignedInteger                       | price * 100 |
| currency               | string(3) default 'USD'               | |
| status                 | string default 'pending'              | pending\|paid\|failed\|canceled |
| paid_at                | timestamp nullable                    | |
| meta                   | json nullable                         | raw gateway response snapshot |
| timestamps             |                                       | |

Order model: belongsTo user, belongsTo course. Scope `pending()`.

## 3. `PaymentGatewayInterface` (app/Services/Payments/Contracts)

```php
interface PaymentGatewayInterface {
    // Returns the data the frontend needs to render the checkout (provider-specific payload).
    public function createCheckout(Order $order): CheckoutSession;
    // Verifies a transaction with the provider AND that the capture matches this order.
    public function confirm(Order $order, string $gatewayId): PaymentResult;
    public function name(): string; // 'payphone' | 'fake'
}
```

`confirm()` takes the **Order**, not a bare `client_transaction_id`, because a
gateway must answer "was THIS order paid?" and not the weaker "did some
transaction succeed?". The payment widget is client-side, so the amount it
charges passed through the browser; the Order row is the only authoritative
record of what should have been captured. Implementations are **required** to
compare the captured amount and currency against it, and to **fail closed**
when the provider response lacks the fields needed to do so.

DTOs:
- `CheckoutSession`: `{ provider, config: array }` — `config` is the exact object the
  frontend passes to the provider widget (see §5 for PayPhone).
- `PaymentResult`: `{ approved: bool, gatewayId: string, status: string, raw: array }`.

Bind via `PaymentServiceProvider` reading `config('services.payments.driver')`
(`payphone` | `fake`). `FakeGateway` is used in tests and local sandbox (always approves
a magic clientTransactionId, declines another) so the whole flow is testable without
hitting PayPhone.

## 4. API endpoints (additions)

| Method | Path                            | Auth | Body | Response |
|--------|---------------------------------|------|------|----------|
| POST   | /api/courses/{slug}/checkout    | yes  | —    | 201 `{ data: { order_id, provider, config } }` |
| POST   | /api/payments/confirm           | yes  | `{ id, clientTransactionId }` | 200 `{ data: { status, enrolled: bool, course_slug } }` |

Guards: checkout 409 if already enrolled; 422 if course is free (use enroll instead).
confirm validates the order belongs to the authenticated user.

## 5. PayPhone Cajita de Pagos v2.0 — exact integration

**Frontend assets** (load on demand in the checkout view):
```html
<link rel="stylesheet" href="https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.css">
<script type="module" src="https://cdn.payphonetodoesposible.com/box/v2.0/payphone-payment-box.js"></script>
```

**`config` object returned by createCheckout** (passed to `PPaymentButtonBox`):
| field                | value |
|----------------------|-------|
| token                | `config('services.payments.payphone.token')` |
| clientTransactionId  | Order.client_transaction_id (max 50 chars) |
| amount               | amount_cents (INTEGER, cents — $1.50 = 150) |
| amountWithoutTax     | amount_cents (no tax in MVP) |
| amountWithTax, tax, service, tip | 0 |
| currency             | "USD" |
| storeId              | `config('services.payments.payphone.store_id')` |
| reference            | "Curso: {course.title}" (max 100) |
| lang                 | "es" |

Constraint: `amount = amountWithoutTax + amountWithTax + tax + service + tip`. With no tax,
set amountWithoutTax = amount and the rest 0.

**Redirect:** after payment PayPhone redirects the browser to the configured response URL
(the frontend callback route, e.g. `http://localhost:5173/payment/callback`) with query
params `?id=<int>&clientTransactionId=<string>`.

**Confirm (server-side, in PayPhoneGateway::confirm):**
```
POST https://paymentbox.payphonetodoesposible.com/api/confirm
Authorization: Bearer <token>
Content-Type: application/json
Body: { "id": <int id from redirect>, "clientTxId": "<clientTransactionId>" }
```
MUST confirm within **5 minutes** of payment or PayPhone auto-reverses.

**Confirm response** (per <https://docs.payphone.app/cajita-de-pagos>):
```json
{ "statusCode": 3, "transactionStatus": "Approved", "amount": 315,
  "currency": "USD", "clientTransactionId": "ID_UNICO_X_TRANSACCION-001",
  "transactionId": 23178284, "authorizationCode": "W23178284", "...": "..." }
```
`statusCode`: **3 = Approved**, **2 = Canceled**.
`amount` is an **INTEGER IN CENTS** — the same unit as `orders.amount_cents`,
so it compares directly with no conversion.

**`statusCode === 3` is NOT sufficient to mark an order paid.** It means only
that *some* transaction was approved. PPaymentButtonBox is a client-side
widget, so the amount from `createCheckout()` travels through the browser
before reaching PayPhone — a tampered amount produces a genuinely approved
capture for the wrong sum. Approving on `statusCode` alone made every priced
item purchasable for one cent.

`PayPhoneGateway::confirm()` therefore verifies, in order, failing closed at
each step:

| Check | Rejected status | Guards against |
|-------|-----------------|----------------|
| `statusCode === 3` | `failed` | declined/cancelled payment |
| `clientTransactionId` matches the order | `transaction_mismatch` | replaying another order's approved transaction |
| `amount` present and numeric | `amount_unverifiable` | provider contract drift (never assume) |
| `amount === order.amount_cents` | `amount_mismatch` | **price tampering** — logged at `critical` |
| `currency === order.currency` | `currency_mismatch` | 50000 COP presented as 50000 USD |

Covered by `tests/Unit/PayPhoneGatewayTest.php` and
`tests/Feature/Checkout/PaymentAmountVerificationTest.php`.

## 6. Config (config/services.php)

```php
'payments' => [
    // No default on purpose: an unset PAYMENT_DRIVER throws in
    // PaymentServiceProvider rather than silently selecting FakeGateway, which
    // approves everything. 'fake' is additionally refused outside local/testing.
    'driver' => env('PAYMENT_DRIVER'), // 'payphone' | 'fake' (required)
    'payphone' => [
        'token'        => env('PAYPHONE_TOKEN'),
        'store_id'     => env('PAYPHONE_STORE_ID'),
        'confirm_url'  => env('PAYPHONE_CONFIRM_URL', 'https://paymentbox.payphonetodoesposible.com/api/confirm'),
    ],
],
```

env keys (user fills from PayPhone Business → Developer → API app):
`PAYMENT_DRIVER`, `PAYPHONE_TOKEN`, `PAYPHONE_STORE_ID`.
Frontend env: `VITE_PAYMENT_CALLBACK_URL` (defaults to `${origin}/payment/callback`).

## 7. Tests

- Backend feature tests use `PAYMENT_DRIVER=fake`: checkout creates pending order + returns
  config; confirm with the "approved" magic id marks order paid + creates enrollment
  (idempotent on second call); confirm with "declined" leaves order failed, no enrollment;
  free course → checkout 422; already enrolled → 409.
- `PayPhoneGateway::confirm` unit test: mock Laravel `Http::fake` returning `{statusCode:3}`
  → approved true; other statusCode → approved false; assert correct URL/body/Bearer header.
