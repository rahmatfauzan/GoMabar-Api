<?php

namespace App\Services;

use App\Models\Field;
use Carbon\Carbon;

class AvailabilityService
{
    /**
     * Mengambil semua slot ketersediaan untuk 1 lapangan di tanggal tertentu.
     * Ini digunakan oleh FieldController.
     */
    public function getSlotsForField(Field $field, Carbon $requestedDate): array
    {
        // Cek 1: Status Lapangan
        if ($field->status !== 'active') {
            return []; // Kosong jika lapangan tidak aktif
        }

        $dayOfWeek = $requestedDate->dayOfWeekIso;

        // LANGKAH 1: Buat Cetakan Slot dari Jam Operasional
        $operatingHours = $field->operatingHours()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$operatingHours) {
            return []; // Lapangan tutup hari itu
        }

        $slots = [];
        $currentTime = Carbon::parse($operatingHours->start_time);
        $endTime = Carbon::parse($operatingHours->end_time);

        while ($currentTime < $endTime) {
            $timeString = $currentTime->format('H:i');
            $slots[$timeString] = [
                'time' => $timeString,
                'is_available' => true,
                'reason' => ""
            ];
            $currentTime->addHour();
        }

        if (empty($slots)) {
            return [];
        }

        // LANGKAH 2: Ambil SEMUA Data Pemblokir (Blocks & Bookings)

        $blocks = $field->blocks()
            ->where('start_datetime', '<=', $requestedDate->copy()->endOfDay())
            ->where('end_datetime', '>=', $requestedDate->copy()->startOfDay())
            ->get();

        $bookings = $field->bookings()
            ->where('booking_date', $requestedDate->toDateString())
            ->where(function ($query) {
                $query->where('status', 'active') // 'active' = paid
                    ->orWhere(function ($subq) {
                        $subq->where('status', 'waiting_payment') // 'waiting_payment' = pending
                            ->where('created_at', '>=', Carbon::now()->subMinutes(15));
                    });
            })
            ->get();

        $allBookedHours = [];
        foreach ($bookings as $booking) {
            $allBookedHours = array_merge($allBookedHours, $booking->booked_slots);
        }
        $allBookedHours = array_unique($allBookedHours);


        // LANGKAH 3: Loop SLOT (Hanya Sekali) dan Coret
        foreach ($slots as $timeString => &$slot) {
            $slotStartTime = $requestedDate->copy()->setTimeFromTimeString($timeString);
            $slotEndTime = $slotStartTime->copy()->addHour();

            // Cek 1: Apakah kena Blokir?
            foreach ($blocks as $block) {
                $blockStartDT = Carbon::parse($block->start_datetime);
                $blockEndDT = Carbon::parse($block->end_datetime);
                if ($slotStartTime < $blockEndDT && $slotEndTime > $blockStartDT) {
                    $slot['is_available'] = false;
                    $slot['reason'] = $block->reason ?? 'Blokir';
                    continue 2;
                }
            }

            // Cek 2: Apakah sudah di-Booking?
            if (in_array($timeString, $allBookedHours)) {
                $slot['is_available'] = false;
                $slot['reason'] = 'Sudah Dipesan';
                continue;
            }
        }
        unset($slot);

        // LANGKAH 4: Kembalikan Hasil
        return array_values($slots);
    }


    /**
     * Mengecek ketersediaan SATU slot spesifik.
     * Ini akan digunakan oleh BookingService saat validasi.
     */
    public function isSlotAvailable(Field $field, Carbon $date, string $timeString): bool
    {
        $slots = $this->getSlotsForField($field, $date);

        foreach ($slots as $slot) {
            if ($slot['time'] === $timeString) {
                return $slot['is_available'];
            }
        }

        // Jika jam tidak ditemukan di jam operasional
        return false;
    }
}
