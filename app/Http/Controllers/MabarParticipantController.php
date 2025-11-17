<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MabarSession;
use App\Models\MabarParticipant;
use App\Http\Resources\MabarParticipantResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MabarParticipantController extends Controller
{
    /**
     * Izinkan Player untuk bergabung ke sesi Mabar.
     * Rute: POST /api/mabar-sessions/{mabarSession}/join
     */
    public function join(Request $request, MabarSession $mabarSession)
    {
        $user = Auth::user();

        $mabarstatus = $mabarSession->load('booking')->booking->status;

        // 1. Cek Sesi (Harus 'awaiting_payment' atau 'confirmed')
        if ($mabarstatus !== "active") {
            return response()->json(['message' => 'Sesi mabar ini tidak sedang menerima peserta.'], 403);
        }

        // 2. Cek apakah user sudah join
        if ($mabarSession->participants()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Anda sudah bergabung dengan sesi mabar ini.'], 409); // Conflict
        }

        // 3. Cek slot (Gunakan hitungan yang efisien)
        $filledSlots = $mabarSession->participants()
            ->whereIn('status', ['approved', 'awaiting_approval', 'waiting_payment'])
            ->count();
        if ($filledSlots >= $mabarSession->slots_total) {
            return response()->json(['message' => 'Maaf, sesi mabar ini sudah penuh.'], 409);
        }

        // 4. Buat record partisipan
        $participant = $mabarSession->participants()->create([
            'user_id' => $user->id,
            'status' => 'waiting_payment' // Status awal saat join (menunggu upload bukti)
        ]);

        return response()->json(new MabarParticipantResource($participant), 201);
    }

    /**
     * Izinkan Host untuk menambah peserta secara manual.
     * Rute: POST /api/mabar-sessions/{mabarSession}/add-manual
     */
    public function addManual(Request $request, MabarSession $mabarSession)
    {
        $host = Auth::user();

        // 1. Otorisasi: Pastikan yang request adalah Host
        if ($mabarSession->host_user_id !== $host->id) {
            return response()->json(['message' => 'Hanya host yang bisa menambah peserta manual.'], 403);
        }

        // 2. Validasi Input
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'guest_name' => 'nullable|required_without:user_id|string|max:255',
        ]);

        // 3. Cek slot
        $filledSlots = $mabarSession->participants()
            ->whereIn('status', ['approved', 'awaiting_approval', 'waiting_payment'])
            ->count();
        if ($filledSlots >= $mabarSession->slots_total) {
            return response()->json(['message' => 'Sesi mabar sudah penuh.'], 409);
        }

        // 4. Cek duplikat user_id (jika diinput)
        if (isset($validated['user_id'])) {
            if ($mabarSession->participants()->where('user_id', $validated['user_id'])->exists()) {
                return response()->json(['message' => 'Pengguna ini sudah ada dalam sesi.'], 409);
            }
        }

        // 5. Buat record partisipan manual
        $participant = $mabarSession->participants()->create([
            'user_id' => $validated['user_id'] ?? null,
            'guest_name' => $validated['guest_name'] ?? null,
            'status' => 'approved' // Ditambah manual oleh Host = otomatis approved
        ]);

        return response()->json(new MabarParticipantResource($participant->load('user')), 201);
    }

    /**
     * Player mengunggah bukti bayar.
     * Rute: POST /api/mabar-participants/upload-proof
     */
    public function uploadProof(Request $request)
    {
        $validated = $request->validate([
            'mabar_session_id' => 'required|integer|exists:mabar_participants,id',
            'payment_proof_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048' // Max 2MB
        ]);

        $user = Auth::user();
        // dd($validated);

        // Cari partisipan yang sesuai
        $mabarParticipant = MabarParticipant::where('id', $validated['mabar_session_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$mabarParticipant) {
            return response()->json(['message' => 'Anda bukan partisipan di sesi mabar ini.'], 403);
        }

        if ($mabarParticipant->status !== 'waiting_payment') {
            return response()->json(['message' => 'Status partisipan tidak memungkinkan untuk unggah bukti.'], 400);
        }

        try {
            // Hapus bukti lama jika ada
            if ($mabarParticipant->payment_proof_image) {
                Storage::disk('public')->delete($mabarParticipant->payment_proof_image);
            }

            // Simpan file baru
            $path = $request->file('payment_proof_image')->store('mabar_proofs', 'public');

            // Update record partisipan
            $mabarParticipant->update([
                'payment_proof_image' => $path,
                'status' => 'awaiting_approval' // Status berubah menunggu Host
            ]);

            return new MabarParticipantResource($mabarParticipant);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal mengunggah bukti pembayaran.'], 500);
        }
    }

    /**
     * Host mengubah status partisipan (Approve/Reject).
     * Rute: PATCH /api/mabar-participants/{mabarParticipant}/status
     */
    public function updateStatus(Request $request, MabarParticipant $mabarParticipant)
    {
        $host = Auth::user();
        $mabarSession = $mabarParticipant->mabarSession;

        // 1. Otorisasi: Pastikan yang approve adalah Host
        if ($mabarSession->host_user_id !== $host->id) {
            return response()->json(['message' => 'Hanya host yang bisa mengubah status partisipan.'], 403);
        }

        // 2. Validasi Input Status
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $newStatus = $validated['status'];

        // 3. Validasi Status Partisipan (Hanya bisa ubah yg 'awaiting_approval')
        if ($mabarParticipant->status !== 'awaiting_approval') {
            return response()->json(['message' => 'Status partisipan tidak memungkinkan untuk diubah.'], 400);
        }

        // 4. Update Status
        $imagePath = $mabarParticipant->payment_proof_image;
        $mabarParticipant->update(['status' => $newStatus]);

        // Jika ditolak, hapus file buktinya
        if ($newStatus === 'rejected') {
            $mabarParticipant->update(['payment_proof_image' => null]);
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
        }

        return new MabarParticipantResource($mabarParticipant);
    }

    public function cancelParticipation(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'mabar_participant_id' => 'required|integer|exists:mabar_participants,id',
        ]);

        $mabarParticipant = MabarParticipant::find($validated['mabar_participant_id']);

        // Cek apakah partisipan milik user yang sedang login
        if ($mabarParticipant->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak berhak membatalkan partisipasi ini.'], 403);
        }

        // Hapus bukti pembayaran jika ada
        if ($mabarParticipant->payment_proof_image) {
            Storage::disk('public')->delete($mabarParticipant->payment_proof_image);
        }

        // Hapus record partisipan
        $mabarParticipant->delete();

        return response()->json(['message' => 'Partisipasi berhasil dibatalkan.'], 200);
    }

    public function destroy(MabarParticipant $mabarParticipant)
    {
        $host = Auth::user();
        $mabarSession = $mabarParticipant->mabarSession;

        // 1. Otorisasi: Pastikan yang menghapus adalah Host
        if ($mabarSession->host_user_id !== $host->id) {
            return response()->json(['message' => 'Hanya host yang bisa menghapus partisipan.'], 403);
        }

        // 2. Hapus bukti pembayaran jika ada
        if ($mabarParticipant->payment_proof_image) {
            Storage::disk('public')->delete($mabarParticipant->payment_proof_image);
        }

        // 3. Hapus record partisipan
        $mabarParticipant->delete();

        return response()->json(['message' => 'Partisipan berhasil dihapus.'], 200);
    }
}
