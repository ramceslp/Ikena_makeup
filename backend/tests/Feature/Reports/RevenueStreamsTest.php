<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Reports\Money\RevenueStreams;
use App\Reports\Money\StreamKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RevenueStreamsTest
 *
 * Lives in the `Reports` testsuite (backend/phpunit.mysql.xml) — runs on
 * BOTH SQLite (default CI) and MySQL (backend-tests-mysql job). Pins design
 * D4's stream table: each of the five streams must read the right
 * source/amount/time-anchor, and NOTHING outside `RevenueStreams` may ever
 * enumerate `orders` for appointment money — see
 * `RevenueSourceIsolationTest::test_only_revenue_streams_may_reference_the_orders_table_or_order_model`
 * for the static half of that guard; this file is the behavioural half.
 */
class RevenueStreamsTest extends TestCase
{
    use RefreshDatabase;

    private function streams(): RevenueStreams
    {
        return new RevenueStreams();
    }

    private function makeInstructor(): User
    {
        return User::factory()->instructor()->create();
    }

    // -------------------------------------------------------------------------
    // course_sale — orders, type=course, status=paid, amount_cents, paid_at
    // -------------------------------------------------------------------------

    public function test_course_sale_stream_reads_paid_course_orders_only(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);

        $paid = Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-course-paid',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Pending course order — must NOT count.
        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-course-pending',
            'gateway' => 'fake',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $streams = $this->streams();
        $total = (int) $streams->query(StreamKey::CourseSale)->sum($streams->amountColumn(StreamKey::CourseSale));

        $this->assertSame(9900, $total);
        $this->assertSame('amount_cents', $streams->amountColumn(StreamKey::CourseSale));
        $this->assertSame('paid_at', $streams->anchorColumn(StreamKey::CourseSale));

        $onlyPaid = $streams->query(StreamKey::CourseSale)->pluck('id')->all();
        $this->assertSame([$paid->id], $onlyPaid);
    }

    // -------------------------------------------------------------------------
    // product_sale — orders, type=product_cart, status=paid, amount_cents, paid_at
    // -------------------------------------------------------------------------

    public function test_product_sale_stream_reads_paid_product_cart_orders_only(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(); // not directly referenced — order_items are PR4a scope

        $paid = Order::create([
            'user_id' => $user->id,
            'type' => 'product_cart',
            'client_transaction_id' => 'ORD-product-paid',
            'gateway' => 'fake',
            'amount_cents' => 4500,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // A course order — must never leak into product_sale.
        $course = Course::factory()->create(['instructor_id' => $this->makeInstructor()->id]);
        Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'type' => 'course',
            'client_transaction_id' => 'ORD-course-control',
            'gateway' => 'fake',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $streams = $this->streams();
        $total = (int) $streams->query(StreamKey::ProductSale)->sum($streams->amountColumn(StreamKey::ProductSale));

        $this->assertSame(4500, $total);

        $onlyProduct = $streams->query(StreamKey::ProductSale)->pluck('id')->all();
        $this->assertSame([$paid->id], $onlyProduct);
    }

    // -------------------------------------------------------------------------
    // appointment_deposit / appointment_deposit_retained — split on status
    // -------------------------------------------------------------------------

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

    public function test_appointment_deposit_stream_excludes_cancelled_appointments(): void
    {
        $active = $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'status' => 'confirmed',
        ]);

        $cancelled = $this->makeAppointment([
            'deposit_collected_cents' => 3000,
            'deposit_collected_at' => now(),
            'status' => 'cancelled',
        ]);

        $streams = $this->streams();
        $depositTotal = (int) $streams->query(StreamKey::AppointmentDeposit)
            ->sum($streams->amountColumn(StreamKey::AppointmentDeposit));
        $retainedTotal = (int) $streams->query(StreamKey::AppointmentDepositRetained)
            ->sum($streams->amountColumn(StreamKey::AppointmentDepositRetained));

        $this->assertSame(2000, $depositTotal);
        $this->assertSame(3000, $retainedTotal);
        $this->assertSame('deposit_collected_cents', $streams->amountColumn(StreamKey::AppointmentDeposit));
        $this->assertSame('deposit_collected_at', $streams->anchorColumn(StreamKey::AppointmentDeposit));
        $this->assertSame('deposit_collected_cents', $streams->amountColumn(StreamKey::AppointmentDepositRetained));

        $activeIds = $streams->query(StreamKey::AppointmentDeposit)->pluck('id')->all();
        $this->assertSame([$active->id], $activeIds);
        $retainedIds = $streams->query(StreamKey::AppointmentDepositRetained)->pluck('id')->all();
        $this->assertSame([$cancelled->id], $retainedIds);
    }

    // -------------------------------------------------------------------------
    // appointment_settlement — every status, settled_amount_cents, settled_at
    // -------------------------------------------------------------------------

    public function test_appointment_settlement_stream_reads_settled_amount_regardless_of_status(): void
    {
        $settled = $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'settled_amount_cents' => 6000,
            'settled_at' => now(),
            'status' => 'paid',
        ]);

        // Not yet settled — must NOT count.
        $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'status' => 'confirmed',
        ]);

        $streams = $this->streams();
        $total = (int) $streams->query(StreamKey::AppointmentSettlement)
            ->sum($streams->amountColumn(StreamKey::AppointmentSettlement));

        $this->assertSame(6000, $total);
        $this->assertSame('settled_amount_cents', $streams->amountColumn(StreamKey::AppointmentSettlement));
        $this->assertSame('settled_at', $streams->anchorColumn(StreamKey::AppointmentSettlement));

        $settledIds = $streams->query(StreamKey::AppointmentSettlement)->pluck('id')->all();
        $this->assertSame([$settled->id], $settledIds);
    }

    // -------------------------------------------------------------------------
    // Appointment orders are structurally invisible to every stream
    // -------------------------------------------------------------------------

    /**
     * design D4: "Appointment orders are structurally invisible to the money
     * layer — they are never enumerated." An `orders` row of type=appointment
     * must not appear under course_sale or product_sale (it fails their type
     * filter) and RevenueStreams never queries `orders` for deposit/
     * settlement money in the first place (those come from `appointments`
     * directly) — so it cannot leak into any of the five streams.
     */
    public function test_appointment_type_orders_never_appear_in_any_stream(): void
    {
        $appointment = $this->makeAppointment([
            'deposit_collected_cents' => 2000,
            'deposit_collected_at' => now(),
            'status' => 'confirmed',
        ]);
        $user = User::factory()->create();

        Order::create([
            'user_id' => $user->id,
            'appointment_id' => $appointment->id,
            'type' => 'appointment',
            'client_transaction_id' => 'ORD-appt-invisible',
            'gateway' => 'fake',
            'amount_cents' => 2000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $streams = $this->streams();
        $courseTotal = (int) $streams->query(StreamKey::CourseSale)->sum($streams->amountColumn(StreamKey::CourseSale));
        $productTotal = (int) $streams->query(StreamKey::ProductSale)->sum($streams->amountColumn(StreamKey::ProductSale));

        $this->assertSame(0, $courseTotal);
        $this->assertSame(0, $productTotal);
    }
}
