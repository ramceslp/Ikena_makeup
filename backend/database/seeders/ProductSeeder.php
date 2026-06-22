<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Idempotent seeder: safe to run repeatedly. Products are keyed on their
     * unique slug, and each product's images are keyed on (product_id, sort_order)
     * so re-seeding reconciles existing rows instead of duplicating them.
     *
     * Image paths use absolute https URLs (Product::resolveImageUrl returns them
     * as-is), so thumbnails render without any file present on the public disk —
     * the same approach courses use for their thumbnails.
     */
    public function run(): void
    {
        // Categories are seeded by CategorySeeder. Resolve them by slug so this
        // seeder works whether or not CategorySeeder ran in the same batch.
        $categories = Category::pluck('id', 'slug');

        $products = [
            [
                'title'        => 'Base de Maquillaje Larga Duración',
                'category'     => 'noche',
                'description'  => 'Base de cobertura media a alta con acabado natural que resiste hasta 16 horas. Fórmula libre de aceites, ideal para pieles mixtas y grasas.',
                'price'        => 38.00,
                'stock_qty'    => 24,
                'images'       => [
                    'https://picsum.photos/seed/ikena-foundation-a/640/640',
                    'https://picsum.photos/seed/ikena-foundation-b/640/640',
                ],
            ],
            [
                'title'        => 'Paleta de Sombras Nude Essentials',
                'category'     => 'editorial',
                'description'  => 'Doce tonos neutros entre mate y satinado para construir desde looks de día sutiles hasta ahumados de noche. Alta pigmentación y difuminado sin esfuerzo.',
                'price'        => 45.00,
                'stock_qty'    => 16,
                'images'       => [
                    'https://picsum.photos/seed/ikena-palette-a/640/640',
                    'https://picsum.photos/seed/ikena-palette-b/640/640',
                ],
            ],
            [
                'title'        => 'Labial Mate Rojo Clásico',
                'category'     => 'noche',
                'description'  => 'Labial mate de larga duración en un rojo atemporal. Confortable, no reseca y deja un acabado aterciopelado intenso.',
                'price'        => 22.00,
                'stock_qty'    => 40,
                'images'       => [
                    'https://picsum.photos/seed/ikena-lipstick/640/640',
                ],
            ],
            [
                'title'        => 'Set de Brochas Profesionales (12 piezas)',
                'category'     => null,
                'description'  => 'Kit completo de doce brochas de fibra sintética suave para rostro y ojos, con estuche de viaje. Aplicación precisa y fácil limpieza.',
                'price'        => 65.00,
                'stock_qty'    => 12,
                'images'       => [
                    'https://picsum.photos/seed/ikena-brushes-a/640/640',
                    'https://picsum.photos/seed/ikena-brushes-b/640/640',
                ],
            ],
            [
                'title'        => 'Iluminador Líquido Dorado',
                'category'     => 'novias',
                'description'  => 'Iluminador líquido de acabado luminoso natural. Se usa solo, mezclado con la base o sobre el maquillaje terminado para un glow de novia impecable.',
                'price'        => 28.00,
                'stock_qty'    => 3,
                'images'       => [
                    'https://picsum.photos/seed/ikena-highlighter/640/640',
                ],
            ],
            [
                'title'        => 'Máscara de Pestañas Volumen Extremo',
                'category'     => 'editorial',
                'description'  => 'Máscara de pestañas que aporta volumen y longitud desde la primera capa, sin grumos. Resistente al agua y de fácil remoción.',
                'price'        => 19.00,
                'stock_qty'    => 0,
                'images'       => [
                    'https://picsum.photos/seed/ikena-mascara/640/640',
                ],
            ],
        ];

        foreach ($products as $data) {
            $product = Product::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'category_id'  => $data['category'] ? $categories->get($data['category']) : null,
                    'title'        => $data['title'],
                    'description'  => $data['description'],
                    'price'        => $data['price'],
                    'stock_qty'    => $data['stock_qty'],
                    'is_published' => true,
                ]
            );

            foreach ($data['images'] as $index => $url) {
                ProductImage::updateOrCreate(
                    ['product_id' => $product->id, 'sort_order' => $index],
                    ['path' => $url]
                );
            }
        }
    }
}
