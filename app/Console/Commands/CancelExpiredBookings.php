<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelExpiredBookings extends Command
{
    protected $signature = 'bookings:cancel-expired';
    protected $description = 'Cancel bookings that have been waiting payment for more than 15 minutes';

    public function handle()
    {
        $this->info('🔍 Checking for expired bookings...');

        // Get bookings yang waiting_payment > 15 menit
        $expiredBookings = Booking::where('status', 'waiting_payment')
            ->where('created_at', '<=', Carbon::now()->subMinutes(15))
            // ->with('transaction')
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info('✅ No expired bookings found.');
            return 0;
        }

        $count = 0;
        foreach ($expiredBookings as $booking) {
            try {


                // Update booking status
                $booking->update([
                    'status' => 'failed',
                    'expired_at' => now(),
                ]);

                // Update transaction status
                if ($booking->transaction) {
                    $booking->transaction->update([
                        'status' => 'expired',
                    ]);
                }


                $count++;

                Log::info('⏰ Booking expired', [
                    'invoice' => $booking->invoice_number,
                    'created_at' => $booking->created_at,
                    'expired_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Failed to expire booking: ' . $e->getMessage(), [
                    'booking_id' => $booking->id,
                ]);
            }
        }

        $this->info("✅ Cancelled {$count} expired bookings.");
        return 0;
    }
}
