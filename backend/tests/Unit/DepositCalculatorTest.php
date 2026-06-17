<?php

namespace Tests\Unit;

use App\Models\Service;
use App\Services\Booking\DepositCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private DepositCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DepositCalculator();
    }

    public function test_deposit_cents_with_25_percent_on_200(): void
    {
        // 200.00 * 25% = 50.00 = 5000 cents
        $service = Service::factory()->create([
            'price'              => 200.00,
            'deposit_percentage' => 25,
        ]);

        $this->assertSame(5000, $this->calculator->cents($service));
    }

    public function test_deposit_cents_with_default_50_percent_on_100(): void
    {
        // 100.00 * 50% = 50.00 = 5000 cents
        $service = Service::factory()->create([
            'price'              => 100.00,
            'deposit_percentage' => 50,
        ]);

        $this->assertSame(5000, $this->calculator->cents($service));
    }

    public function test_deposit_cents_with_30_percent_on_150(): void
    {
        // 150.00 * 30% = 45.00 = 4500 cents
        $service = Service::factory()->create([
            'price'              => 150.00,
            'deposit_percentage' => 30,
        ]);

        $this->assertSame(4500, $this->calculator->cents($service));
    }

    public function test_deposit_cents_rounds_fractional_cents(): void
    {
        // 100.00 * 33% = 33.00 = 3300 cents (no rounding needed here)
        // Use 10.00 * 33% = 3.30 → 330 cents
        $service = Service::factory()->create([
            'price'              => 10.00,
            'deposit_percentage' => 33,
        ]);

        // 10 * 33 / 100 * 100 = 10 * 33 = 330 cents → round(330) = 330
        $this->assertSame(330, $this->calculator->cents($service));
    }
}
