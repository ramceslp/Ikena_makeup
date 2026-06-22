<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    /**
     * Idempotent seeder: services are keyed on their unique slug; each service's
     * images are keyed on (service_id, sort_order). Image paths use absolute
     * https URLs (Service::resolveImageUrl returns them as-is) so thumbnails
     * render without any file on the public disk.
     */
    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');

        $services = [
            [
                'title'             => 'Maquillaje de Novia',
                'category'          => 'novias',
                'description'       => 'Maquillaje profesional de larga duración para el día de tu boda, con prueba previa incluida. Técnicas resistentes al agua y retoque garantizado.',
                'price'             => 150.00,
                'duration_hours'    => 3,
                'availability_type' => 'by_appointment',
                'deposit_percentage' => 50,
                'images'            => ['https://picsum.photos/seed/ikena-svc-bride/800/600'],
            ],
            [
                'title'             => 'Maquillaje Social y de Noche',
                'category'          => 'noche',
                'description'       => 'Look impecable para eventos, fiestas y celebraciones. Acabado glam o natural según la ocasión, pensado para durar toda la noche.',
                'price'             => 80.00,
                'duration_hours'    => 2,
                'availability_type' => 'by_appointment',
                'deposit_percentage' => 40,
                'images'            => ['https://picsum.photos/seed/ikena-svc-night/800/600'],
            ],
            [
                'title'             => 'Maquillaje Editorial y Books',
                'category'          => 'editorial',
                'description'       => 'Maquillaje creativo para sesiones fotográficas, editoriales y books profesionales. Diseñado en conjunto con la propuesta del shooting.',
                'price'             => 200.00,
                'duration_hours'    => 4,
                'availability_type' => 'by_appointment',
                'deposit_percentage' => 50,
                'images'            => ['https://picsum.photos/seed/ikena-svc-editorial/800/600'],
            ],
            [
                'title'             => 'Asesoría de Imagen Express',
                'category'          => null,
                'description'       => 'Sesión corta de asesoría personalizada: análisis de tono de piel, recomendación de productos y rutina básica adaptada a vos.',
                'price'             => 40.00,
                'duration_hours'    => 1,
                'availability_type' => 'immediate',
                'deposit_percentage' => 100,
                'images'            => ['https://picsum.photos/seed/ikena-svc-advice/800/600'],
            ],
        ];

        foreach ($services as $data) {
            $service = Service::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'category_id'        => $data['category'] ? $categories->get($data['category']) : null,
                    'title'              => $data['title'],
                    'description'        => $data['description'],
                    'price'              => $data['price'],
                    'duration_hours'     => $data['duration_hours'],
                    'availability_type'  => $data['availability_type'],
                    'deposit_percentage' => $data['deposit_percentage'],
                    'is_published'       => true,
                ]
            );

            foreach ($data['images'] as $index => $url) {
                ServiceImage::updateOrCreate(
                    ['service_id' => $service->id, 'sort_order' => $index],
                    ['path' => $url]
                );
            }
        }
    }
}
