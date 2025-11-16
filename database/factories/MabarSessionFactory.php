<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\MabarSession;
use App\Models\Booking; // <-- Import Booking
use App\Models\User;
use App\Models\Role;

class MabarSessionFactory extends Factory
{
    protected $model = MabarSession::class;

    public function definition(): array
    {
        // 1. Ambil Host (atau buat jika perlu)
        $host = User::whereHas('roles', fn($q) => $q->where('name', 'user'))->inRandomOrder()->first();
        if (!$host) {
            $host = User::factory()->create();
            $host->roles()->attach(Role::firstOrCreate(['name' => 'user']));
        }

        // 2. BUAT BOOKING DULU (untuk mendapatkan booking_id)
        $booking = Booking::factory()->create([
            'user_id' => $host->id, // Host adalah pemesan
            'status' => 'waiting_payment', // Status booking-nya
        ]);

        $slots_total = rand(8, 12);

        // 3. Hitung harga iuran (price_per_slot)
        $price_per_slot = 0;
        if ($slots_total > 0) {
            $price_per_slot = ceil($booking->price / $slots_total);
        }

        // 4. Buat Mabar Session (sekarang DENGAN booking_id)
        return [
            'host_user_id' => $host->id,
            'booking_id' => $booking->id, // <-- ID SUDAH ADA (TIDAK NULL)
            'title' => $this->faker->words(3, true) . ' Fun Mabar',
            'type' => $this->faker->randomElement(['open_play', 'team_challenge']),
            'description' => $this->faker->sentence,
            'slots_total' => $slots_total,
            'price_per_slot' => $price_per_slot, // <-- Harga sudah benar
            'payment_instructions' => 'Transfer ke ' . $host->name . ' di BCA 123456.',
        ];
    }

    /**
     * Konfigurasi factory (setelah dibuat).
     * Kita masih perlu ini untuk 2 hal:
     * 1. Menautkan balik booking->mabar_session_id
     * 2. Menambahkan host sebagai partisipan
     */
    public function configure(): static
    {
        return $this->afterCreating(function (MabarSession $session) {

            $session->participants()->create([
                'user_id' => $session->host_user_id,
                'status' => 'approved',
            ]);
        });
    }
}
