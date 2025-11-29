<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;
use App\Models\MabarSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getRecentBookings()
    {
        $bookings = Booking::with('user', 'field') // Asumsi relasi
            ->latest() // Urutkan dari yang terbaru
            ->take(5)  // Ambil 5 saja
            ->get();

        // Kita format agar sesuai dengan tipe 'Booking' di frontend
        $formattedBookings = $bookings->map(function ($booking) {
            return [
                'id' => $booking->id,
                'invoice_number' => $booking->invoice_number, // Ganti 'invoice' jika nama kolom beda
                'customer_name' => $booking->name_orders ?? $booking->user->name, // Ambil dari relasi
                'field' => [
                    'id' => $booking->field->id,
                    'name' => $booking->field->name,
                ],
                'price' => $booking->price,
                'booked_status' => $booking->status,
            ];
        });

        return response()->json($formattedBookings);
    }

    public function getStats()
    {
        $today = Carbon::today();

        // 1. Total Pendapatan HARI INI (Hanya Booking Lunas, Non-Mabar)
        $totalRevenue = Booking::where('status', 'active')
            ->whereDate('created_at', $today)
            ->whereDoesntHave('mabarSession') // <-- KECUALIKAN BOOKING MABAR
            ->sum('price');

        // 2. Total Booking HARI INI (Non-Cancelled, Non-Mabar)
        $totalBookings = Booking::where('status', '!=', 'cancelled')
            ->whereDate('created_at', $today)
            ->whereDoesntHave('mabarSession') // <-- KECUALIKAN BOOKING MABAR
            ->count();

        // 3. Total Pengguna BARU HARI INI (Asumsi: User yang terdaftar hari ini)
        $totalNewUsersToday = User::whereHas('roles', fn($q) => $q->where('name', 'user'))
            ->whereDate('created_at', $today) // Filter hanya yang dibuat hari ini
            ->count();

        // 4. Mabar Aktif (sesi yang masih berjalan/menunggu)
        $activeMabarCount = MabarSession::with(['booking' => function ($q) {
            $q->where('status', '!=', 'cancelled');
        }])->count();

        // Cocokkan nama dengan 'DashboardStats' di frontend
        return response()->json([
            'revenue' => (int)$totalRevenue,
            'bookings' => $totalBookings,
            'newUsers' => $totalNewUsersToday, // Perbaiki: Kirim hitungan user BARU hari ini
            'activeMabar' => $activeMabarCount,
        ]);
    }


    public function getUpcomingMabar()
    {
        $upcomingSessions = MabarSession::with([
            'host:id,name',
            'booking:id,booking_date,booked_slots,field_id', // Memilih kolom yang dibutuhkan dari tabel bookings
            'booking.field:id,name'
        ])
            ->whereHas('booking', function ($query) {
                $query->where('status', 'active')
                    ->whereDate('booking_date', '>=', Carbon::now());
            })
            ->latest()
            ->take(2)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'type' => $session->type,
                    'host' => $session->host->name,
                    'field' => $session->booking->field->name,
                    'date' => $session->booking->booking_date ? $session->booking->booking_date->format('Y-m-d') : null,
                    'slots' => $session->booking->booked_slots,
                ];
            });

        return response()->json($upcomingSessions);
    }
}
