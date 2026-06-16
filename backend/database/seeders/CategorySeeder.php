<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Idempotently seed the three fixed categories.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Editorial', 'slug' => 'editorial'],
            ['name' => 'Novias',    'slug' => 'novias'],
            ['name' => 'Noche',     'slug' => 'noche'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name']]
            );
        }
    }
}
