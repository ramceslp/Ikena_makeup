<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use App\Reports\PeriodCalendar;
use App\Reports\Queries\LedgerQuery;
use App\Reports\ReportFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * LedgerQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml) — runs on SQLite AND MySQL.
 * Pins spec's "Sales ledger" requirement: a paginated, filterable
 * cross-source row list, sourced exclusively through `RevenueStreams`
 * (design D4) via UNION ALL — never `orders.total_cents`, never a direct
 * `Order::`/`'orders'` reference (see `RevenueSourceIsolationTest`'s static
 * guard, which this file must not trip).
 */
class LedgerQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): LedgerQuery
    {
        return new LedgerQuery(new RevenueStreams());
    }

    private function filterFor(string $from, string $to, array $extra = []): ReportFilter
    {
        $request = Request::create('/api/admin/reports/ledger', 'GET', array_merge([
            'from' => $from,
            'to' => $to,
        ], $extra));

        return ReportFilter::fromRequest($request, new PeriodCalendar());
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        $service = Service::factory()->create([
            'availability_type' => 'by_appointment',
            'is_published' => true,
            'price' => 80.00,
            'deposit_percentage' => 30,
        ]);
        $user = User::factory()->create();

        return Appointment::create(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'slot_key' => "{$service->id}|2026-08-10|10:00|".uniqid(),
            'whatsapp' => '+593099912345',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 2400,
            'service_price_cents' => 8000,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_ledger_lists_rows_across_every_stream_ordered_by_most_recent_first(): void
    {
        $buyer = User::factory()->create(['name' => 'Ana Buyer']);
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        Order::create([
            'user_id' => $buyer->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-ledger-course',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ]);

        $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => '2026-08-06 10:00:00',
            'status' => 'confirmed',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(2, $result->total());
        $rows = collect($result->items());

        // Most recent occurred_at first — the outer UNION order, not a
        // per-branch order.
        $this->assertSame('appointment_deposit', $rows[0]->stream);
        $this->assertSame(2000, (int) $rows[0]->amount_cents);
        $this->assertSame('course_sale', $rows[1]->stream);
        $this->assertSame(9900, (int) $rows[1]->amount_cents);
        $this->assertSame('Ana Buyer', $rows[1]->counterparty);
        $this->assertSame('Venta de curso', $rows[1]->label);
    }

    public function test_ledger_excludes_rows_outside_the_date_range(): void
    {
        Order::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-ledger-out-of-range',
            'gateway' => 'fake',
            'amount_cents' => 4500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-01-01 10:00:00',
        ]);

        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(0, $result->total());
    }

    public function test_ledger_can_be_filtered_to_a_single_stream(): void
    {
        Order::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-ledger-product',
            'gateway' => 'fake',
            'amount_cents' => 4500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => '2026-08-05 10:00:00',
        ]);

        $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => '2026-08-06 10:00:00',
            'status' => 'confirmed',
        ]);

        $filter = $this->filterFor('2026-08-01', '2026-08-31', ['stream' => [StreamKey::ProductSale->value]]);
        $result = $this->query()->run($filter);

        $this->assertSame(1, $result->total());
        $this->assertSame('product_sale', collect($result->items())[0]->stream);
    }

    public function test_ledger_paginates_at_the_default_page_size(): void
    {
        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(25, $result->perPage());
    }

    public function test_ledger_is_empty_when_nothing_happened_in_range(): void
    {
        $result = $this->query()->run($this->filterFor('2026-08-01', '2026-08-31'));

        $this->assertSame(0, $result->total());
        $this->assertSame([], $result->items());
    }
}
