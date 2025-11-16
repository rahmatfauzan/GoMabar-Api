<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Buat Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']); // 'user' untuk player

        // 2. Buat Admin
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'), // Ganti password jika perlu
            'phone' => "081234567890",
            // 'address' => '...',
        ])->roles()->attach($adminRole);

        // 3. Buat User (Contoh Player)
        User::factory()->create([
            'name' => 'Player User',
            'email' => 'player@example.com',
            'password' => Hash::make('password'),
            'phone' => "089876543210",
        ])->roles()->attach($userRole);

        // 4. Buat 20 User dummy tambahan
        User::factory(20)->create()->each(function ($user) use ($userRole) {
            $user->roles()->attach($userRole);
        });

        // 5. Panggil Seeder Lain (URUTAN PENTING)
        $this->call([
            SportCategorySeeder::class, // Harus ada sebelum FieldSeeder
            FieldSeeder::class,           // Harus ada sebelum BookingSeeder
            BookingSeeder::class,         // Panggil seeder gabungan (Booking, Mabar, Transaksi)
        ]);
    }
}