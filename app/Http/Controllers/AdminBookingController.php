<?php

namespace App\Http\Controllers; // Pastikan namespace-nya benar

use App\Http\Controllers\Controller;
use App\Models\Booking; // <-- Import Model
use App\Http\Resources\BookingResource; // <-- Import Resource
use App\Models\Field;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminBookingController extends Controller
{
    public function __construct(private BookingService $bookingService) {}


    

    public function index(Request $request)
    {
        // 1. Validasi filter (opsional tapi bagus)
        $request->validate([
            'status' => 'nullable|in:waiting_payment,active,failed,cancelled',
            'date_from' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string',
        ]);

        // 2. Mulai Kueri
        // Kita pakai 'query()' agar bisa menambah filter
        $query = Booking::query();

        // 3. Terapkan Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('booking_date', '>=', $request->date_from);
        }

        // Contoh filter pencarian (invoice atau nama)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('name_orders', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // 4. Eager Load Relasi (agar efisien)
        // Muat data 'user' dan 'field' bersamaan
        $query->with(['user', 'field']);

        // 5. Urutkan & Paginasi
        // Tampilkan yang terbaru, 15 per halaman
        $bookings = $query->latest()->paginate(15);

        // 6. Kembalikan sebagai Resource Collection
        return BookingResource::collection($bookings);
    }

    public function manualStore(Request $request)
    {
        $validated = $request->validate([
            'field_id' => 'required|exists:fields,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'booked_slots' => 'required|array|min:1',
            'booked_slots.*' => 'required|date_format:H:i|distinct',

            // Pilih salah satu: user terdaftar ATAU tamu
            'user_id' => 'nullable|exists:users,id',
            'name_orders' => 'required_without:user_id|string|max:255',
            'phone_orders' => 'required_without:user_id|string|max:20',
        ]);

        $field = Field::findOrFail($validated['field_id']);
        $date = Carbon::parse($validated['booking_date']);
        $user = isset($validated['user_id']) ? User::find($validated['user_id']) : null;
        $guestData = $request->only(['name_orders', 'phone_orders']);

        try {
            $booking = null;

            // Kita gunakan DB Transaction untuk memastikan Booking & Transaksi dibuat bersamaan
            DB::transaction(function () use ($field, $date, $validated, $user, $guestData, &$booking) {

                // 1. Panggil Service untuk buat booking
                // Kita set statusnya langsung 'active' (lunas)
                $booking = $this->bookingService->createBooking(
                    $field,
                    $date,
                    $validated['booked_slots'],
                    $user,
                    $guestData,
                    'active' // <-- Langsung 'active' (lunas)
                );

                // 2. Buat Transaksi "Cash"
                $booking->transaction()->create([
                    'amount' => $booking->price,
                    'status' => 'success',
                    'payment_gateway' => 'cash', // <-- Tandai sebagai cash
                ]);
            });

            // Load relasi untuk respons
            // $booking->load('field', 'transaction', 'user');

            return response()->json(new BookingResource($booking), 201);
        } catch (\Exception $e) {
            // Tangkap error dari service (misal: "Slot sudah dipesan")
            $statusCode = $e->getCode() === 409 ? 409 : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
}
