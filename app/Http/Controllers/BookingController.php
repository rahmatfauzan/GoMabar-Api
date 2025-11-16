<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\MidtransService;
use App\Models\Field;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private MidtransService $midtransService
    ) {}

    /**
     * Create new booking
     */



    public function store(Request $request)
    {
        $validated = $request->validate([
            'field_id' => 'required|exists:fields,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'booked_slots' => 'required|array|min:1',
            'booked_slots.*' => 'required|date_format:H:i|distinct',
            'name_orders' => 'nullable|string|max:255',
            'phone_orders' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();

        try {
            $field = Field::findOrFail($validated['field_id']);
            $date = Carbon::parse($validated['booking_date']);
            $user = Auth::user();

            // Guest validation
            $guestData = [];
            if (!$user) {
                if (empty($validated['name_orders']) || empty($validated['phone_orders'])) {
                    return response()->json([
                        'message' => 'Nama dan nomor telepon wajib diisi untuk pemesanan tanpa login'
                    ], 422);
                }
                $guestData = [
                    'name_orders' => $validated['name_orders'],
                    'phone_orders' => $validated['phone_orders'],
                ];
            }

            // Create booking
            $booking = $this->bookingService->createBooking(
                $field,
                $date,
                $validated['booked_slots'],
                $user,
                $guestData,
                'waiting_payment'
            );

            // Create transaction
            $transaction = Transaction::create([
                'booking_id' => $booking->id,
                'amount' => $booking->price,
                'status' => 'pending',
                'payment_gateway' => 'midtrans',
            ]);

            // Prepare customer details
            $customerDetails = [
                'first_name' => $user ? $user->name : $validated['name_orders'],
                'phone' => $user ? ($user->phone ?? $validated['phone_orders']) : $validated['phone_orders'],
            ];

            if ($user && $user->email) {
                $customerDetails['email'] = $user->email;
            }

            // Generate Midtrans snap token
            $snapToken = $this->midtransService->createSnapToken($booking, $customerDetails);

            // Update transaction
            $transaction->update(['gateway_token' => $snapToken]);

            DB::commit();

            return response()->json([
                'message' => 'Booking berhasil dibuat',
                'data' => [
                    'booking' => new BookingResource($booking),
                    'snap_token' => $snapToken,
                    'invoice_number' => $booking->invoice_number,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            $statusCode = $e->getCode() === 409 ? 409 : 500;
            Log::error('Booking store failed: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'field_id' => $validated['field_id'] ?? null,
            ]);

            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Get booking by invoice number
     */
    // Di BookingController.php
    // Di BookingController.php
    public function getByInvoice($invoiceNumber)
    {
        $booking = Booking::where('invoice_number', $invoiceNumber)
            ->with(['field', 'transaction', 'user'])
            ->firstOrFail();

        // Check ownership
        if ($booking->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        $transaction = $booking->transaction;

        // Hitung remaining time dari transaction
        $expiresAt = $booking->created_at->copy()->addMinutes(15);
        $remainingSeconds = max(0, now()->diffInSeconds($expiresAt, false));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $booking->id,
                'invoice_number' => $booking->invoice_number,
                'booking_date' => $booking->booking_date,
                'booked_slots' => $booking->booked_slots,
                'price' => $booking->price,
                'status' => $booking->status,
                'created_at' => $booking->created_at,
                'field' => $booking->field,
                'transaction' => [
                    'id' => $transaction->id,
                    'gateway_token' => $transaction->gateway_token,
                    'expires_at' => $expiresAt,
                    'remaining_seconds' => $remainingSeconds,
                    'status' => $transaction->status,
                ],
                'user' => $booking->user ? [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                    'phone' => $booking->user->phone,
                ] : null,
            ]
        ]);
    }

    /**
     * Get single booking by ID
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $booking = Booking::with(['field', 'transaction'])
                ->findOrFail($id);

            // Check ownership
            if ($booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking tidak ditemukan'
            ], 404);
        }
    }

    /**
     * Cancel booking
     */
    public function cancel($id)
    {
        try {
            $user = Auth::user();
            $booking = Booking::findOrFail($id);

            // Check ownership
            if ($booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.'
                ], 403);
            }

            // Check if can cancel
            if (in_array($booking->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking tidak dapat dibatalkan'
                ], 400);
            }

            // Check 24 hours rule
            $bookingDateTime = Carbon::parse($booking->booking_date . ' ' . $booking->booked_slots[0]);
            $hoursDiff = now()->diffInHours($bookingDateTime, false);

            if ($hoursDiff < 24) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking hanya dapat dibatalkan minimal 24 jam sebelum jadwal'
                ], 400);
            }

            // Update status
            $booking->update(['status' => 'cancelled']);

            if ($booking->transaction && $booking->transaction->status === 'success') {
                $booking->transaction->update(['status' => 'refunded']);
            }

            Log::info('Booking cancelled', [
                'booking_id' => $booking->id,
                'invoice' => $booking->invoice_number,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking berhasil dibatalkan',
                'data' => new BookingResource($booking)
            ]);
        } catch (\Exception $e) {
            Log::error('Cancel booking failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan booking'
            ], 500);
        }
    }

    /**
     * Regenerate payment token for pending booking
     */
    public function getToken($invoiceNumber)
    {
        try {
            // dd('invoiceNumber', $invoiceNumber);
            $user = Auth::user();
            $booking = Booking::where('invoice_number', $invoiceNumber)
                ->with(['field', 'transaction'])
                ->firstOrFail();

            // Check ownership
            if ($booking->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden.'
                ], 403);
            }

            // Check if still pending
            if (!in_array($booking->status, ['waiting_payment', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking ini tidak dapat dibayar lagi. Status: ' . $booking->status
                ], 400);
            }

            $transaction = $booking->transaction;

            // Check if transaction exists
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            // Check expiry based on transaction created_at (not booking)
            // Token Midtrans biasanya valid 24 jam
            $expiryHours = 24; // atau ambil dari config
            $transactionAge = $transaction->created_at->diffInHours(now());

            if ($transactionAge >= $expiryHours) {
                $booking->update(['status' => 'expired']);
                $transaction->update(['status' => 'expired']);

                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi sudah expired. Silakan buat booking baru.'
                ], 400);
            }

            // Return existing token (TIDAK PERLU BUAT TOKEN BARU!)
            $snapToken = $transaction->gateway_token;

            // Jika token tidak ada (edge case), baru buat baru
            // if (!$snapToken) {
            //     $customerDetails = [
            //         'first_name' => $booking->user->name,
            //         'phone' => $booking->user->phone ?? $booking->phone_orders,
            //         'email' => $booking->user->email,
            //     ];

            //     $snapToken = $this->midtransService->createSnapToken($booking, $customerDetails);
            //     $transaction->update(['gateway_token' => $snapToken]);
            // }

            // Calculate remaining time
            $remainingMinutes = ($expiryHours * 60) - $transaction->created_at->diffInMinutes(now());

            Log::info('Payment token retrieved', ['invoice' => $invoiceNumber]);

            return response()->json([
                'success' => true,
                'message' => 'Token pembayaran berhasil diambil',
                'data' => [
                    'snap_token' => $snapToken,
                    'invoice_number' => $booking->invoice_number,
                    'expires_in_minutes' => max(0, $remainingMinutes),
                    'created_at' => $transaction->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get payment token failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil token pembayaran'
            ], 500);
        }
    }

    /**
     * Get all user bookings with filter
     */
    public function myBookings(Request $request)
    {
        $user = Auth::user();
        $status = $request->query('status');
        $perPage = $request->query('per_page', 10);

        $query = Booking::with(['field', 'transaction'])
            ->where('user_id', $user->id)
            ->orderBy('booking_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ]);
    }

    /**
     * Get active bookings
     */
    public function activeBookings()
    {
        $user = Auth::user();

        $bookings = Booking::with(['field', 'transaction'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('booking_date', '>=', now()->toDateString())
            ->orderBy('booking_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'total' => $bookings->count(),
        ]);
    }
    /**
     * Get pending bookings (waiting payment)
     */
    public function pendingBookings()
    {
        $user = Auth::user();

        $bookings = Booking::with(['field', 'transaction'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['waiting_payment', 'pending'])
            ->where('created_at', '>=', now()->subMinutes(15))
            ->orderBy('created_at', 'desc')
            ->get();

        // Add expiry info
        $bookings->map(function ($booking) {
            $expiresAt = $booking->created_at->addMinutes(15);
            $minutesLeft = now()->diffInMinutes($expiresAt, false);

            $booking->expires_at = $expiresAt;
            $booking->minutes_remaining = max(0, $minutesLeft);
            $booking->is_expired = $minutesLeft <= 0;

            return $booking;
        });

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'total' => $bookings->count(),
        ]);
    }

    /**
     * Get booking history
     */
    public function bookingFailed(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);

        $bookings = Booking::with(['field', 'transaction'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['failed'])
            ->orderBy('booking_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ]);
    }

    /**
     * Get completed bookings
     */
    public function completedBookings(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 10);

        $bookings = Booking::with(['field', 'transaction'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('booking_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ]
        ]);
    }

    /**
     * Get booking statistics
     */
    public function getStatistics()
    {
        $user = Auth::user();

        $stats = [
            'active' => Booking::where('user_id', $user->id)
                ->where('status', 'active')
                ->count(),

            'pending' => Booking::where('user_id', $user->id)
                ->whereIn('status', ['waiting_payment', 'pending'])
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count(),

            'upcoming' => Booking::where('user_id', $user->id)
                ->where('status', 'active')
                ->whereBetween('booking_date', [
                    now()->toDateString(),
                    now()->addDays(7)->toDateString()
                ])
                ->count(),

            'total_spent' => Booking::where('user_id', $user->id)
                ->where('status', 'active')
                ->sum('price'),

            'total_bookings' => Booking::where('user_id', $user->id)
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
