<?php

namespace Tests\Feature\Products;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('products'));

        $this->assertTrue(Schema::hasColumn('products', 'id'));
        $this->assertTrue(Schema::hasColumn('products', 'category_id'));
        $this->assertTrue(Schema::hasColumn('products', 'title'));
        $this->assertTrue(Schema::hasColumn('products', 'slug'));
        $this->assertTrue(Schema::hasColumn('products', 'description'));
        $this->assertTrue(Schema::hasColumn('products', 'price'));
        $this->assertTrue(Schema::hasColumn('products', 'stock_qty'));
        $this->assertTrue(Schema::hasColumn('products', 'is_published'));
        $this->assertTrue(Schema::hasColumn('products', 'created_at'));
        $this->assertTrue(Schema::hasColumn('products', 'updated_at'));
    }

    public function test_product_images_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('product_images'));

        $this->assertTrue(Schema::hasColumn('product_images', 'id'));
        $this->assertTrue(Schema::hasColumn('product_images', 'product_id'));
        $this->assertTrue(Schema::hasColumn('product_images', 'path'));
        $this->assertTrue(Schema::hasColumn('product_images', 'sort_order'));
        $this->assertTrue(Schema::hasColumn('product_images', 'created_at'));
        $this->assertTrue(Schema::hasColumn('product_images', 'updated_at'));
    }
}
