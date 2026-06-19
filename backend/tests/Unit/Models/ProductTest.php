<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // stock_state accessor
    // -------------------------------------------------------------------------

    public function test_stock_state_returns_out_of_stock_when_qty_is_zero(): void
    {
        $product = Product::factory()->create(['stock_qty' => 0]);

        $this->assertEquals('Agotado', $product->stock_state);
    }

    public function test_stock_state_returns_low_stock_when_qty_equals_threshold(): void
    {
        // low_threshold is 5 from config/commerce.php
        $product = Product::factory()->create(['stock_qty' => 5]);

        $this->assertEquals('Últimas unidades', $product->stock_state);
    }

    public function test_stock_state_returns_low_stock_when_qty_is_one(): void
    {
        $product = Product::factory()->create(['stock_qty' => 1]);

        $this->assertEquals('Últimas unidades', $product->stock_state);
    }

    public function test_stock_state_returns_in_stock_when_qty_is_above_threshold(): void
    {
        // low_threshold is 5, so stock_qty=6 must be "En Stock"
        $product = Product::factory()->create(['stock_qty' => 6]);

        $this->assertEquals('En Stock', $product->stock_state);
    }

    public function test_stock_state_returns_in_stock_when_qty_is_ten(): void
    {
        $product = Product::factory()->create(['stock_qty' => 10]);

        $this->assertEquals('En Stock', $product->stock_state);
    }

    // -------------------------------------------------------------------------
    // category() relationship
    // -------------------------------------------------------------------------

    public function test_product_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $product  = Product::factory()->create(['category_id' => $category->id]);

        $this->assertEquals($category->id, $product->category->id);
    }

    public function test_product_category_can_be_null(): void
    {
        $product = Product::factory()->create(['category_id' => null]);

        $this->assertNull($product->category);
    }

    // -------------------------------------------------------------------------
    // images() relationship — ordered by sort_order
    // -------------------------------------------------------------------------

    public function test_images_relationship_returns_images_ordered_by_sort_order(): void
    {
        $product = Product::factory()->create();
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 2]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

        $orders = $product->images->pluck('sort_order')->toArray();

        $this->assertEquals([0, 1, 2], $orders);
    }
}
