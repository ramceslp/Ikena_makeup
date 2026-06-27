<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent seeder: safe to run repeatedly. Uses updateOrCreate/firstOrCreate
     * keyed on natural unique attributes (email, slug, position) so re-seeding
     * reconciles existing rows instead of failing on unique constraints.
     */
    public function run(): void
    {
        // ---------------------------------------------------------------
        // 0. Categories (must exist before courses/products reference them)
        // ---------------------------------------------------------------
        $this->call(CategorySeeder::class);

        // ---------------------------------------------------------------
        // 0b. Products (physical catalog — depends on categories)
        // ---------------------------------------------------------------
        $this->call(ProductSeeder::class);

        // ---------------------------------------------------------------
        // 0c. Services + their booking slots (depend on categories)
        // ---------------------------------------------------------------
        $this->call(ServiceSeeder::class);
        $this->call(ServiceSlotSeeder::class);

        // ---------------------------------------------------------------
        // 1. Instructor
        // ---------------------------------------------------------------
        $instructor = User::updateOrCreate(
            ['email' => 'instructor@ikena.test'],
            [
                'name'              => 'Ana García',
                'password'          => Hash::make('password'),
                'role'              => 'instructor',
                'email_verified_at' => now(),
            ]
        );

        // ---------------------------------------------------------------
        // 2. Admin
        // ---------------------------------------------------------------
        User::updateOrCreate(
            ['email' => 'admin@ikena.test'],
            [
                'name'              => 'Admin Ikena',
                'password'          => Hash::make('password'),
                'role'              => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // ---------------------------------------------------------------
        // 3. Student (fixed credentials for testing)
        // ---------------------------------------------------------------
        $student = User::updateOrCreate(
            ['email' => 'student@ikena.test'],
            [
                'name'              => 'Carlos López',
                'password'          => Hash::make('password'),
                'role'              => 'student',
                'email_verified_at' => now(),
            ]
        );

        // ---------------------------------------------------------------
        // 4. Course 1 — "Fundamentos del Maquillaje" (student enrolled here)
        // ---------------------------------------------------------------
        $course1 = Course::updateOrCreate(
            ['slug' => 'makeup-fundamentals'],
            [
                'instructor_id' => $instructor->id,
                'title'         => 'Fundamentos del Maquillaje',
                'description'   => 'Aprende las técnicas esenciales del maquillaje profesional desde cero. Este curso completo cubre la preparación de la piel, la teoría del color y las herramientas indispensables para toda artista.',
                'price'         => 49.99,
                'thumbnail'     => 'https://loremflickr.com/640/360/makeup,cosmetics?lock=51',
                'is_published'  => true,
            ]
        );

        $section1_1 = Section::updateOrCreate(
            ['course_id' => $course1->id, 'position' => 0],
            ['title' => 'Primeros Pasos']
        );

        $lesson1_1_1 = Lesson::updateOrCreate(
            ['section_id' => $section1_1->id, 'position' => 0],
            [
                'title'       => 'Bienvenida y Presentación del Curso',
                'description' => 'Una introducción a todo lo que vas a aprender en este curso.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'duration'    => 300,
                'is_free'     => true,
            ]
        );

        $lesson1_1_2 = Lesson::updateOrCreate(
            ['section_id' => $section1_1->id, 'position' => 1],
            [
                'title'       => 'Tu Kit de Inicio',
                'description' => 'Las brochas, esponjas y productos esenciales para toda principiante.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'duration'    => 540,
                'is_free'     => false,
            ]
        );

        $section1_2 = Section::updateOrCreate(
            ['course_id' => $course1->id, 'position' => 1],
            ['title' => 'Preparación de la Piel']
        );

        Lesson::updateOrCreate(
            ['section_id' => $section1_2->id, 'position' => 0],
            [
                'title'       => 'Limpieza, Tonificación e Hidratación',
                'description' => 'Cómo preparar el lienzo de la piel antes de cualquier aplicación de maquillaje.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'duration'    => 720,
                'is_free'     => false,
            ]
        );

        Lesson::updateOrCreate(
            ['section_id' => $section1_2->id, 'position' => 1],
            [
                'title'       => 'Cómo Elegir la Prebase Correcta',
                'description' => 'Prebases para distintos tipos de piel y acabados.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
                'duration'    => 610,
                'is_free'     => false,
            ]
        );

        $section1_3 = Section::updateOrCreate(
            ['course_id' => $course1->id, 'position' => 2],
            ['title' => 'Teoría del Color']
        );

        Lesson::updateOrCreate(
            ['section_id' => $section1_3->id, 'position' => 0],
            [
                'title'       => 'Entendiendo el Círculo Cromático',
                'description' => 'Colores primarios, secundarios y terciarios aplicados al maquillaje.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4',
                'duration'    => 840,
                'is_free'     => true,
            ]
        );

        // ---------------------------------------------------------------
        // 5. Course 2 — "Maestría en Maquillaje de Ojos"
        // ---------------------------------------------------------------
        $course2 = Course::updateOrCreate(
            ['slug' => 'eye-makeup-mastery'],
            [
                'instructor_id' => $instructor->id,
                'title'         => 'Maestría en Maquillaje de Ojos',
                'description'   => 'Domina el arte del maquillaje de ojos con técnicas de ahumados, cut crease, looks con glitter y estilos naturales para el día a día. Apto para todas las formas de ojo.',
                'price'         => 29.99,
                'thumbnail'     => 'https://loremflickr.com/640/360/eye,makeup?lock=52',
                'is_published'  => true,
            ]
        );

        $section2_1 = Section::updateOrCreate(
            ['course_id' => $course2->id, 'position' => 0],
            ['title' => 'Formas y Anatomía del Ojo']
        );

        Lesson::updateOrCreate(
            ['section_id' => $section2_1->id, 'position' => 0],
            [
                'title'       => 'Identifica la Forma de tus Ojos',
                'description' => 'Una guía para determinar la forma única de tus ojos y adaptar los looks.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'duration'    => 480,
                'is_free'     => true,
            ]
        );

        Lesson::updateOrCreate(
            ['section_id' => $section2_1->id, 'position' => 1],
            [
                'title'       => 'Secretos del Difuminado de Sombras',
                'description' => 'Técnicas profesionales de difuminado que elevan cualquier look.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'duration'    => 900,
                'is_free'     => false,
            ]
        );

        $section2_2 = Section::updateOrCreate(
            ['course_id' => $course2->id, 'position' => 1],
            ['title' => 'Looks Emblemáticos']
        );

        Lesson::updateOrCreate(
            ['section_id' => $section2_2->id, 'position' => 0],
            [
                'title'       => 'Ahumado Clásico',
                'description' => 'Ahumado paso a paso que funciona para cualquier ocasión.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'duration'    => 1200,
                'is_free'     => false,
            ]
        );

        // ---------------------------------------------------------------
        // 6. Course 3 — "Maquillaje de Novia Esencial" (free course)
        // ---------------------------------------------------------------
        $course3 = Course::updateOrCreate(
            ['slug' => 'bridal-makeup-essentials'],
            [
                'instructor_id' => $instructor->id,
                'title'         => 'Maquillaje de Novia Esencial',
                'description'   => 'Todo lo que necesitas para crear un look de novia impecable que dure todo el día. Incluye técnicas de base, productos resistentes al agua y estrategias de retoque.',
                'price'         => 0.00,
                'thumbnail'     => 'https://loremflickr.com/640/360/bride,makeup?lock=53',
                'is_published'  => true,
            ]
        );

        $section3_1 = Section::updateOrCreate(
            ['course_id' => $course3->id, 'position' => 0],
            ['title' => 'Preparación de la Novia']
        );

        Lesson::updateOrCreate(
            ['section_id' => $section3_1->id, 'position' => 0],
            [
                'title'       => 'Consulta de Piel para Novias',
                'description' => 'Cómo evaluar las necesidades de la piel y planificar el look semanas antes.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
                'duration'    => 660,
                'is_free'     => true,
            ]
        );

        Lesson::updateOrCreate(
            ['section_id' => $section3_1->id, 'position' => 1],
            [
                'title'       => 'Técnicas de Base de Larga Duración',
                'description' => 'Métodos de capas y fijación para que dure todo el día.',
                'video_url'   => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4',
                'duration'    => 780,
                'is_free'     => true,
            ]
        );

        // ---------------------------------------------------------------
        // 7. Enroll student in Course 1 (idempotent)
        // ---------------------------------------------------------------
        Enrollment::firstOrCreate(
            ['user_id' => $student->id, 'course_id' => $course1->id],
            ['price_paid' => $course1->price]
        );

        // ---------------------------------------------------------------
        // 8. Mark 2 lessons as completed for the student (idempotent)
        // ---------------------------------------------------------------
        $student->completedLessons()->syncWithoutDetaching([
            $lesson1_1_1->id => ['completed_at' => now()],
            $lesson1_1_2->id => ['completed_at' => now()],
        ]);

        // ---------------------------------------------------------------
        // 9. Appointments (need services + users) — one per lifecycle state
        // ---------------------------------------------------------------
        $this->call(AppointmentSeeder::class);

        // ---------------------------------------------------------------
        // 10. Posts / news (need an author user)
        // ---------------------------------------------------------------
        $this->call(PostSeeder::class);

        // ---------------------------------------------------------------
        // 11. Certificate branding singleton (id=1) with defaults
        // ---------------------------------------------------------------
        $this->call(CertificateSettingSeeder::class);
    }
}
