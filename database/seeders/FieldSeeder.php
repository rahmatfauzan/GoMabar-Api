<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Field;
use App\Models\SportCategory;

class FieldSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil ID Kategori
        $futsal = SportCategory::where('name', 'Futsal')->first();
        $badminton = SportCategory::where('name', 'Badminton')->first();

        // Buat Lapangan (panggil factory)
        if ($futsal) {
            Field::factory()->count(10)->create(['sport_category_id' => $futsal->id]);
        }
        if ($badminton) {
            Field::factory()->count(100)->create(['sport_category_id' => $badminton->id]);
        }

        // Ambil SEMUA field yang baru dibuat (atau sudah ada)
        $fields = Field::all();

        // Buat Jam Operasional Default untuk setiap field
        foreach ($fields as $field) {
            for ($day = 1; $day <= 7; $day++) { // 1=Senin, 7=Minggu
                $isWeekend = in_array($day, [6, 7]);
                
                $field->operatingHours()->firstOrCreate(
                    ['day_of_week' => $day],
                    [
                        'start_time' => $isWeekend ? '07:00:00' : '08:00:00',
                        'end_time'   => $isWeekend ? '23:00:00' : '22:00:00',
                    ]
                );
            }
        }
    }
}