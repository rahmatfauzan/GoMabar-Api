<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource; // (Opsional)

class MyParticipantsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->mabarSession->title,
            'user_status' => $this->status,
            'payment_proof_image' => $this->payment_proof_image ? url('storage/' . $this->payment_proof_image) : null,
            'slots_total' => $this->mabarSession->slots_total,
            'price_per_slot' => $this->mabarSession->price_per_slot,
            // 'mabar_status' => $this->mabarSession->status,
            'id_mabar_session' => $this->mabar_session_id,

            // // Cek apakah ini 'guest' atau 'user' terdaftar
            'is_guest' => is_null($this->user_id),
            'name' => $this->user_id ? $this->user->name : $this->guest_name,
            'mabar_status' => $this->mabarSession->booking->status,
            'booking_date' => $this->mabarSession->booking->booking_date,
            'booked_slots' => $this->mabarSession->booking->booked_slots,
            'price' => $this->mabarSession->booking->price,
            // Tampilkan detail user jika di-load (dan bukan guest)
            'user' => new UserResource($this->whenLoaded('user')), 
            // 'mabar' =>new MabarSessionResource($this->whenLoaded('mabarSession')),
            'field'=>$this->mabarSession->booking->field->name,
            'participants_count'=>$this->participants_count
        ];
    }
}