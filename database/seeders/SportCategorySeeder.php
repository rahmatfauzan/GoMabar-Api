<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SportCategory;

class SportCategorySeeder extends Seeder
{
    public function run(): void
    {
        SportCategory::firstOrCreate(['name' => 'Futsal'], ['icon' => '⚽️']);
        SportCategory::firstOrCreate(['name' => 'Badminton'], ['icon' => '🏸']);
        SportCategory::firstOrCreate(['name' => 'Basket'], ['icon' => '🏀']);
    }
}
