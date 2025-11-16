<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\MabarSession;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat 30 booking biasa (status acak)
        Booking::factory(30)->create();
        
        // 2. Buat 10 mabar (otomatis buat 10 booking terkait)
        MabarSession::factory(10)->create();

        // 3. Tambahkan Transaksi untuk booking yg statusnya 'active'
        $paidBookings = Booking::where('status', 'active')->get();
        foreach ($paidBookings as $booking) {
            if (!$booking->transaction) {
                Transaction::factory()
                    ->for($booking) // Otomatis tautkan 'booking_id'
                    ->create(['status' => 'success']);
            }
        }

        // 4. Tambahkan Transaksi untuk booking yg 'waiting_payment'
        $pendingBookings = Booking::where('status', 'waiting_payment')->get();
        foreach ($pendingBookings as $booking) {
            if (!$booking->transaction) {
                Transaction::factory()
                    ->for($booking)
                    ->create(['status' => 'pending']);
            }
        }
    }
}