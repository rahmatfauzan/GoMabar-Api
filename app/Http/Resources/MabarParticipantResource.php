<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource; // (Opsional)

class MabarParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'payment_proof_image' => $this->payment_proof_image ? url('storage/' . $this->payment_proof_image) : null,
            
            // Cek apakah ini 'guest' atau 'user' terdaftar
            'is_guest' => is_null($this->user_id),
            'name' => $this->user_id ? $this->user->name : $this->guest_name,
            
            // Tampilkan detail user jika di-load (dan bukan guest)
            'user' => new UserResource($this->whenLoaded('user')), 
        ];
    }
}