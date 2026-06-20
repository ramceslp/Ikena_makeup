<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\ProductCardResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unit contract tests for ProductCardResource.
 *
 * Verifies the public-facing payload shape:
 *   - stock_state derivation (Spanish labels via getStockStateAttribute)
 *   - price formatted to 2 decimal places as string
 *   - is_published hidden from non-admin / unauthenticated requests
 *   - description truncated to 150 chars
 */
class ProductCardResourceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // stock_state derivation
    // -------------------------------------------------------------------------

    public function test_stock_state_is_en_stock_when_qty_above_threshold(): void
    {
        $product = Product::factory()->create(['stock_qty' => 10, 'is_published' => true]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals('En Stock', $resource['stock_state']);
        $this->assertEquals(10, $resource['stock_qty']);
    }

    public function test_stock_state_is_ultimas_unidades_when_qty_at_threshold(): void
    {
        // threshold = 5 (config commerce.stock.low_threshold)
        $product = Product::factory()->create(['stock_qty' => 5, 'is_published' => true]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals('Últimas unidades', $resource['stock_state']);
        $this->assertEquals(5, $resource['stock_qty']);
    }

    public function test_stock_state_is_ultimas_unidades_when_qty_below_threshold(): void
    {
        $product = Product::factory()->create(['stock_qty' => 3, 'is_published' => true]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals('Últimas unidades', $resource['stock_state']);
    }

    public function test_stock_state_is_agotado_when_qty_is_zero(): void
    {
        $product = Product::factory()->create(['stock_qty' => 0, 'is_published' => true]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals('Agotado', $resource['stock_state']);
        $this->assertEquals(0, $resource['stock_qty']);
    }

    // -------------------------------------------------------------------------
    // Price formatting
    // -------------------------------------------------------------------------

    public function test_price_is_formatted_as_two_decimal_string(): void
    {
        $product = Product::factory()->create(['price' => 120.00, 'stock_qty' => 10]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals('120.00', $resource['price']);
    }

    public function test_price_with_cents_is_formatted_correctly(): void
    {
        $product = Product::factory()->create(['price' => 49.99, 'stock_qty' => 10]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals('49.99', $resource['price']);
    }

    // -------------------------------------------------------------------------
    // is_published visibility gate
    // -------------------------------------------------------------------------

    public function test_is_published_not_exposed_in_unauthenticated_request(): void
    {
        $product = Product::factory()->create(['is_published' => true, 'stock_qty' => 10]);
        $product->load('images', 'category');

        // No authenticated user → no admin → is_published must be absent
        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertArrayNotHasKey('is_published', $resource);
    }

    // -------------------------------------------------------------------------
    // Description truncation
    // -------------------------------------------------------------------------

    public function test_description_is_truncated_to_150_chars(): void
    {
        $longDesc = str_repeat('a', 200);
        $product  = Product::factory()->create([
            'description' => $longDesc,
            'stock_qty'   => 10,
        ]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        // mb_strimwidth width=150 includes the '...' marker; total is exactly 150 chars.
        $this->assertSame(150, mb_strlen($resource['description']));
        $this->assertStringEndsWith('...', $resource['description']);
    }

    public function test_short_description_is_not_truncated(): void
    {
        $short   = 'Short description';
        $product = Product::factory()->create([
            'description' => $short,
            'stock_qty'   => 10,
        ]);
        $product->load('images', 'category');

        $request  = Request::create('/');
        $resource = (new ProductCardResource($product))->toArray($request);

        $this->assertEquals($short, $resource['description']);
    }
}
