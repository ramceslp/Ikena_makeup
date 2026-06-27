<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PostSeeder extends Seeder
{
    /**
     * Idempotent seeder: posts are keyed on their unique slug. Covers the full
     * spread the admin list needs to show: several `type` values, a featured
     * post, and an unpublished draft. cover_image_path uses absolute https URLs
     * (Post::resolveImageUrl returns them as-is) so covers render with no file.
     *
     * Cover photos come from LoremFlickr, matched by keyword per post topic
     * (e.g. /bride,makeup for the bridal-trends article) so each cover relates
     * to its content. The `?lock=N` seed pins a stable image across re-seeds.
     *
     * Valid types: noticia, nuevo_curso, oferta, evento, lanzamiento,
     * certificacion, contenido.
     */
    public function run(): void
    {
        $author = User::firstOrCreate(
            ['email' => 'admin@ikena.test'],
            [
                'name'              => 'Admin Ikena',
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $posts = [
            [
                'title'       => 'Nueva colección de labiales mate ya disponible',
                'type'        => 'oferta',
                'excerpt'     => 'Lanzamos seis tonos nuevos con 20% de descuento por tiempo limitado.',
                'is_featured' => true,
                'cta_label'   => 'Ver productos',
                'cta_url'     => '/products',
                'days_ago'    => 1,
                'image'       => 'lipstick,cosmetics',
                'lock'        => 41,
            ],
            [
                'title'       => 'Curso de automaquillaje: nuevas fechas de inscripción',
                'type'        => 'nuevo_curso',
                'excerpt'     => 'Abrimos cupos para la edición de invierno de nuestro curso más solicitado.',
                'is_featured' => false,
                'cta_label'   => 'Ver cursos',
                'cta_url'     => '/cursos',
                'days_ago'    => 3,
                'image'       => 'makeup,brush',
                'lock'        => 42,
            ],
            [
                'title'       => 'Tendencias de maquillaje para novias 2026',
                'type'        => 'noticia',
                'excerpt'     => 'Pieles luminosas, labios nude y acabados naturales marcan la temporada.',
                'is_featured' => false,
                'cta_label'   => null,
                'cta_url'     => null,
                'days_ago'    => 6,
                'image'       => 'bride,makeup',
                'lock'        => 43,
            ],
            [
                'title'       => 'Masterclass presencial de maquillaje editorial',
                'type'        => 'evento',
                'excerpt'     => 'Una jornada intensiva con sesión fotográfica real. Cupos limitados.',
                'is_featured' => false,
                'cta_label'   => 'Reservar lugar',
                'cta_url'     => '/services',
                'days_ago'    => 10,
                'image'       => 'fashion,makeup',
                'lock'        => 44,
            ],
            [
                'title'       => 'Certificación profesional Ikena disponible',
                'type'        => 'certificacion',
                'excerpt'     => 'Completá la ruta de cursos y obtené tu certificado avalado.',
                'is_featured' => false,
                'cta_label'   => null,
                'cta_url'     => null,
                'days_ago'    => 14,
                'image'       => 'makeup,artist',
                'lock'        => 45,
            ],
            [
                // Draft — exercises the unpublished state in the admin list.
                'title'       => 'Próximamente: línea de cuidado de la piel',
                'type'        => 'lanzamiento',
                'excerpt'     => 'Estamos preparando algo especial. Muy pronto más novedades.',
                'is_featured' => false,
                'cta_label'   => null,
                'cta_url'     => null,
                'days_ago'    => null, // null => draft (unpublished)
                'image'       => 'skincare,cosmetics',
                'lock'        => 46,
            ],
        ];

        foreach ($posts as $data) {
            $isDraft = $data['days_ago'] === null;

            Post::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'author_id'        => $author->id,
                    'title'            => $data['title'],
                    'excerpt'          => $data['excerpt'],
                    'cover_image_path' => 'https://loremflickr.com/1200/630/' . $data['image'] . '?lock=' . $data['lock'],
                    'body'             => '<p>' . $data['excerpt'] . '</p><p>Contenido de ejemplo generado por el seeder para previsualizar la sección de novedades.</p>',
                    'type'             => $data['type'],
                    'is_featured'      => $data['is_featured'],
                    'cta_label'        => $data['cta_label'],
                    'cta_url'          => $data['cta_url'],
                    'is_published'     => ! $isDraft,
                    'published_at'     => $isDraft ? null : now()->subDays($data['days_ago']),
                ]
            );
        }
    }
}
