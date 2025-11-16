<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteExpiredBookings extends Command
{
    protected $signature = 'bookings:complete-expired';
    protected $description = 'Mark bookings as completed if payment is success and play time has passed';

    public function handle()
    {
        $this->info('🔍 Checking for completed bookings...');

        // Get active bookings yang waktu bermainnya sudah lewat
        $bookings = Booking::where('status', 'active')
            ->whereDate('booking_date', '<', now()->toDateString())
            ->orWhere(function ($query) {
                // Atau hari ini tapi jam terakhir sudah lewat
                $query->whereDate('booking_date', '=', now()->toDateString())
                    ->where('status', 'active');
            })
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            // Cek jam terakhir booking
            $lastSlot = collect($booking->booked_slots)->last();
            $bookingEndTime = Carbon::parse($booking->booking_date . ' ' . $lastSlot)->addHour();

            // Jika waktu bermain sudah lewat
            if (now()->greaterThan($bookingEndTime)) {
                $booking->update(['status' => 'completed']);
                $count++;

                Log::info('✅ Booking completed', [
                    'invoice' => $booking->invoice_number,
                    'booking_date' => $booking->booking_date,
                    'last_slot' => $lastSlot,
                ]);

                $this->info("✅ Completed: {$booking->invoice_number}");
            }
        }

        $this->info("✅ Marked {$count} bookings as completed.");
        return 0;
    }
}
