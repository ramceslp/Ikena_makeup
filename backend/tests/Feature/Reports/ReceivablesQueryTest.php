<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Reports\Queries\ReceivablesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ReceivablesQueryTest
 *
 * Reports testsuite (backend/phpunit.mysql.xml). Pins spec's three
 * receivable-bucket requirements ([Slice 4]) and design D6's bucket table:
 * A (unconfirmed reservation, FULL price, excluded from the cash-flow
 * projection), B (scheduled balance), C (overdue balance, 24h grace) —
 * mutually exclusive and complete over every unsettled, non-cancelled
 * appointment.
 */
class ReceivablesQueryTest extends TestCase
{
    use RefreshDatabase;

    private function query(): ReceivablesQuery
    {
        return new ReceivablesQuery();
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        $service = Service::factory()->create();
        $user = User::factory()->create();

        return Appointment::create(array_merge([
            'service_id' => $service->id,
            'user_id' => $user->id,
            'order_id' => null,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'slot_key' => "{$service->id}|2026-08-10|10:00|".uniqid(),
            'whatsapp' => '+593099912345',
            'payment_mode' => 'gateway',
            'deposit_amount_cents' => 2400,
            'service_price_cents' => 8000,
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_bucket_a_is_a_future_appointment_with_unpaid_deposit_at_full_price(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00');

        $this->makeAppointment([
            'scheduled_date' => '2026-08-04',
            'deposit_collected_at' => null,
            'deposit_collected_cents' => 0,
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(1, $result['bucket_a']['count']);
        $this->assertSame(8000, $result['bucket_a']['outstanding_cents']);
        $this->assertSame(0, $result['bucket_b']['count']);
        $this->assertSame(0, $result['bucket_c']['count']);
        // Bucket A is excluded from the cash-flow projection.
        $this->assertSame(0, $result['projection_cents']);
        $this->assertSame(8000, $result['total_receivable_cents']);
    }

    public function test_bucket_b_is_a_future_appointment_with_paid_deposit_at_the_balance(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00');

        $this->makeAppointment([
            'scheduled_date' => '2026-08-06',
            'deposit_collected_at' => '2026-07-28 10:00:00',
            'deposit_collected_cents' => 2000,
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(1, $result['bucket_b']['count']);
        $this->assertSame(6000, $result['bucket_b']['outstanding_cents']);
        $this->assertSame(0, $result['bucket_a']['count']);
        $this->assertSame(0, $result['bucket_c']['count']);
        $this->assertSame(6000, $result['projection_cents']);
    }

    public function test_bucket_c_is_overdue_by_more_than_the_24h_grace_period(): void
    {
        $now = Carbon::parse('2026-08-10 12:00:00');

        // scheduled_end_time was 2026-08-09 11:00 — 25 hours before $now.
        $this->makeAppointment([
            'scheduled_date' => '2026-08-09',
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'deposit_collected_at' => '2026-08-05 10:00:00',
            'deposit_collected_cents' => 2000,
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(1, $result['bucket_c']['count']);
        $this->assertSame(6000, $result['bucket_c']['outstanding_cents']);
        $this->assertSame(0, $result['bucket_a']['count']);
        $this->assertSame(0, $result['bucket_b']['count']);
    }

    public function test_23_hours_overdue_still_falls_in_bucket_b_within_grace(): void
    {
        $now = Carbon::parse('2026-08-10 10:00:00');

        // scheduled_end_time was 2026-08-09 11:00 — 23 hours before $now.
        $this->makeAppointment([
            'scheduled_date' => '2026-08-09',
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'deposit_collected_at' => '2026-08-05 10:00:00',
            'deposit_collected_cents' => 2000,
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(1, $result['bucket_b']['count']);
        $this->assertSame(0, $result['bucket_c']['count']);
    }

    public function test_settled_appointments_never_appear_in_any_bucket(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00');

        $this->makeAppointment([
            'scheduled_date' => '2026-07-01', // long overdue
            'deposit_collected_at' => '2026-06-25 10:00:00',
            'deposit_collected_cents' => 2000,
            'settled_amount_cents' => 6000,
            'settled_at' => '2026-07-02 10:00:00',
            'status' => 'paid',
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(0, $result['bucket_a']['count'] + $result['bucket_b']['count'] + $result['bucket_c']['count']);
        $this->assertSame(0, $result['total_receivable_cents']);
    }

    public function test_cancelled_appointments_never_appear_in_any_bucket(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00');

        $this->makeAppointment([
            'scheduled_date' => '2026-08-06',
            'deposit_collected_at' => '2026-07-28 10:00:00',
            'deposit_collected_cents' => 2000,
            'status' => 'cancelled',
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(0, $result['bucket_a']['count'] + $result['bucket_b']['count'] + $result['bucket_c']['count']);
    }

    public function test_buckets_partition_a_mixed_fixture_without_overlap_or_gap(): void
    {
        $now = Carbon::parse('2026-08-10 12:00:00');

        // Bucket A: future, unpaid deposit, full price = 8000.
        $this->makeAppointment([
            'scheduled_date' => '2026-08-15',
            'deposit_collected_at' => null,
            'deposit_collected_cents' => 0,
        ]);

        // Bucket B: future, paid deposit, balance = 6000.
        $this->makeAppointment([
            'scheduled_date' => '2026-08-15',
            'deposit_collected_at' => '2026-08-01 10:00:00',
            'deposit_collected_cents' => 2000,
        ]);

        // Bucket C: overdue by 25h, balance = 6000.
        $this->makeAppointment([
            'scheduled_date' => '2026-08-09',
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '11:00',
            'deposit_collected_at' => '2026-08-01 10:00:00',
            'deposit_collected_cents' => 2000,
        ]);

        $result = $this->query()->run($now);

        $this->assertSame(20000, $result['total_receivable_cents']);
        $this->assertSame(12000, $result['projection_cents']);
    }
}
