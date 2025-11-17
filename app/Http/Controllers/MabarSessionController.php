<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\MabarIndexResource;
use App\Http\Resources\MabarParticipantResource;
use App\Models\MabarSession;
use App\Models\Booking;
use App\Models\Field;
use App\Http\Resources\MabarSessionResource;
use App\Http\Resources\MyParticipantsResource;
use App\Http\Resources\UserSessionsResource;
use App\Models\MabarParticipant;
use App\Models\Transaction;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class MabarSessionController extends Controller
{

    private $user;

    // Injeksi BookingService
    public function __construct(private BookingService $bookingService, private \App\Services\MidtransService $midtransService)
    {
        $this->user = Auth::user();
    }

    public function index(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'type' => 'nullable|in:open_play,team_challenge,mini_tournament',
            'status' => 'nullable|in:active,waiting_payment,failed,cancelled', // Status Booking
        ]);
        /** @var \App\Models\User */
        // Perbaikan Cek Admin: Harus menggunakan Auth::user() jika ada
        $user = Auth::user();
        $is_admin = $user ? $user->roles()->where('name', 'admin')->exists() : false;

        // 2. Definisikan Status Booking Default Berdasarkan Peran
        if ($is_admin) {
            // ADMIN: Ambil booking yang relevan
            $allowedBookingStatuses = ['active', 'waiting_payment', 'failed', 'cancelled'];
        } else {
            // PUBLIK: HANYA yang 'active'
            $allowedBookingStatuses = ['active'];
        }

        // 3. APPLY FILTER OPTIONAL DARI REQUEST ($request->status)
        if ($request->filled('status') && in_array($validated['status'], $allowedBookingStatuses)) {
            // Jika status spesifik diminta dan diizinkan, timpa array default
            $allowedBookingStatuses = [$validated['status']];
        }

        // 4. Mabar Session Query
        $query = MabarSession::query()->latest();

        // 5. FILTER UTAMA: Gunakan whereHas untuk memfilter sesi Mabar berdasarkan status relasi Booking
        $query->whereHas('booking', function ($q) use ($allowedBookingStatuses) {
            $q->whereIn('status', $allowedBookingStatuses);
        });

        // 6. Eager Loading (Sama)
        $query->with([
            'host:id,name,email,phone,address',
            'booking' => function ($query) {
                $query->select('id', 'field_id', 'booking_date', 'booked_slots', 'status')

                    // --- TAMBAHAN KRITIS: LOAD TRANSACTION ---
                    ->with(['transaction' => function ($q) {
                        $q->select('booking_id', 'id');
                    }])

                    ->with(['field' => function ($query) {
                        $query->select('id', 'name', 'sport_category_id')
                            ->with('sportCategory:id,name,icon');
                    }]);
            }
        ]);

        // 7. Hitungan Partisipan
        $query->withCount(['participants' => function ($q) {
            $q->whereIn('status', ['approved', 'awaiting_approval', 'waiting_payment']);
        }]);

        $sessions = $query->paginate(10);

        return MabarIndexResource::collection($sessions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['required', Rule::in(['open_play', 'team_challenge', 'mini_tournament'])],
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'slots_total' => 'required|integer|min:2',
            'price_per_slot' => 'required|integer|min:0',
            'payment_instructions' => 'required|string|max:1000',
            'field_id' => 'required|exists:fields,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'booked_slots' => 'required|array|min:1',
            'booked_slots.*' => 'required|date_format:H:i|distinct',
        ]);

        $field = Field::findOrFail($validated['field_id']);
        $date = Carbon::parse($validated['booking_date']);
        $slots = $validated['booked_slots'];
        $coverImagePath = null;

        try {
            DB::beginTransaction();

            $booking = $this->bookingService->createBooking(
                $field,
                $date,
                $slots,
                $this->user,
                [],
                'waiting_payment'
            );

            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('mabar_covers', 'public');
            }

            $mabarSession = MabarSession::create([
                'host_user_id' => $this->user->id,
                'booking_id' => $booking->id,
                'title' => $validated['title'],
                'type' => $validated['type'],
                'status' => $validated['price_per_slot'] > 0 ? 'awaiting_payment' : 'approved',
                'description' => $validated['description'] ?? null,
                'cover_image' => $coverImagePath,
                'slots_total' => $validated['slots_total'],
                'price_per_slot' => $validated['price_per_slot'],
                'payment_instructions' => $validated['payment_instructions'],
            ]);

            $mabarSession->participants()->create([
                'user_id' => $this->user->id,
                'status' => 'approved'
            ]);
            
            $transaction = Transaction::create([
                'booking_id' => $booking->id,
                'amount' => $booking->price,
                'status' => 'pending',
                'payment_gateway' => 'midtrans',
            ]);

            $user = Auth::user();
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

            $mabarSession->load('host', 'participants.user', 'booking.field');

            return response()->json([
                'message' => 'Sesi Mabar dan Booking berhasil dibuat. Segera lakukan pembayaran.',
                'mabar_session' => new MabarSessionResource($mabarSession),
                'booking_to_pay' => new BookingResource($booking)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($coverImagePath) Storage::disk('public')->delete($coverImagePath);

            $statusCode = $e->getCode() === 409 ? 409 : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    public function show(MabarSession $mabarSession)
    {
        $mabarSession->load('host', 'participants', 'booking');
        $mabarSession->loadCount(['participants' => function ($query) {
            $query->whereIn('status', ['approved', 'awaiting_approval', 'waiting_payment']);
        }]);
        return new MabarSessionResource($mabarSession);
    }

    public function destroy(MabarSession $mabarSession)
    {
        $isAdmin = $this->user->roles->contains('name', 'admin');

        if (!$isAdmin && $mabarSession->host_user_id !== $this->user->id) {
            return response()->json(['message' => 'Hanya host atau admin yang bisa menghapus sesi ini.'], 403);
        }

        return response()->json(null, 204);
    }


    public function userSessions(Request $request)
    {
        $sessions = MabarSession::where('host_user_id', $this->user->id)
            ->with('host', 'booking.field', 'participants')
            ->withCount(['participants' => function ($query) {
                $query->whereIn('status', ['approved', 'awaiting_approval', 'waiting_payment']);
            }])
            ->latest()
            ->paginate(15);

        return UserSessionsResource::collection($sessions);
    }

    public function userJoinedSessions(Request $request)
    {
        $participant = MabarParticipant::where('user_id', auth()->id())
            ->with([
                'mabarSession',
                'mabarSession.host:id,name',
                'mabarSession.booking:id,status,booking_date,booked_slots,price,field_id',
                'mabarSession.booking.field',
            ])
            ->withCount([
                'sessionParticipants as participants_count' => function ($q) {
                    $q->whereIn('status', [
                        'approved',
                        'awaiting_approval',
                        'waiting_payment',
                    ]);
                }
            ])
            ->get();
            // dd($participant);

        return MyParticipantsResource::collection($participant);
    }

    public function update(Request $request, MabarSession $mabarSession)
    {
        $isAdmin = $this->user->roles->contains('name', 'admin');

        if (!$isAdmin && $mabarSession->host_user_id !== $this->user->id) {
            return response()->json(['message' => 'Hanya host atau admin yang bisa memperbarui sesi ini.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'type' => ['sometimes', 'required', Rule::in(['open_play', 'team_challenge', 'mini_tournament'])],
            'description' => 'sometimes|nullable|string',
            'cover_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'slots_total' => 'sometimes|required|integer|min:2',
            'price_per_slot' => 'sometimes|required|integer|min:0',
            'payment_instructions' => 'sometimes|required|string|max:1000',
        ]);


        $imagePath = $mabarSession->cover_image;

        if ($request->hasFile('cover_image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('cover_image')->store('mabar_covers', 'public');
            $validated['cover_image'] = $imagePath; // Set path baru untuk di-update
        }

        $mabarSession->update($validated);

        return new MabarSessionResource($mabarSession);
    }
}
