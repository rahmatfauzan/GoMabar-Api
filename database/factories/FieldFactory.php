<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SportCategory;

class FieldFactory extends Factory
{
    protected $model = \App\Models\Field::class;

    public function definition(): array
    {
        // Ambil kategori acak (Pastikan Seeder Kategori jalan dulu)
        $category = SportCategory::inRandomOrder()->first();
        if (!$category) {
            $category = SportCategory::factory()->create(['name' => 'Default Category']);
        }

        return [
            'sport_category_id' => $category->id,
            'name' => $this->faker->word() . ' Field ' . $this->faker->randomLetter(),
            'description' => $this->faker->sentence(),
            'field_photo' => null,
            'price_weekday' => $this->faker->numberBetween(10, 20) * 10000, // 100rb - 200rb
            'price_weekend' => $this->faker->numberBetween(15, 25) * 10000, // 150rb - 250rb
            'status' => 'active',
        ];
    }
}