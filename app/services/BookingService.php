<?php

namespace App\Services;

use App\Models\Field;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\AvailabilityService; // <-- Import service ketersediaan
use Exception;

class BookingService
{
    // Injeksi service ketersediaan
    public function __construct(private AvailabilityService $availabilityService)
    {
    }

    /**
     * Logika inti untuk membuat pesanan booking.
     * Dipanggil oleh BookingController (player) & AdminBookingController (admin).
     */
    public function createBooking(
        Field $field,
        Carbon $date,
        array $slots,
        User $user = null, // User yg login (bisa null jika admin input manual)
        array $guestData = [], // Data tamu (jika admin input manual)
        string $status = 'waiting_payment' // Status awal
    ): Booking {
        
        // 1. Validasi Ketersediaan (Panggil Service Lain)
        foreach ($slots as $timeString) {
            if (!$this->availabilityService->isSlotAvailable($field, $date, $timeString)) {
                // Jika satu slot saja sudah diambil, gagalkan
                throw new Exception("Maaf, slot jam {$timeString} sudah dipesan orang lain.", 409);
            }
        }

        // 2. Kalkulasi Harga
        $isWeekend = $date->isWeekend();
        $pricePerSlot = $isWeekend ? $field->price_weekend : $field->price_weekday;
        $totalPrice = count($slots) * $pricePerSlot;

        // 3. Simpan ke Database (Pakai Transaction)
        $booking = DB::transaction(function () use (
            $field, $date, $slots, $user, $guestData, $totalPrice, $pricePerSlot, $status
        ) {
            
            // Buat "Slot Booking" (Struk Utama)
            $booking = Booking::create([
                'user_id' => $user->id ?? null,
                'name_orders' => $guestData['name_orders'] ?? null,
                'phone_orders' => $guestData['phone_orders'] ?? null,
                
                'invoice_number' => 'INV-' . strtoupper(uniqid()),
                'status' => $status,
                
                'field_id' => $field->id,
                'booking_date' => $date->toDateString(),
                'booked_slots' => $slots, // Simpan array jam
                'price' => $totalPrice,
            ]);

            return $booking;
        });

        return $booking;
    }
}