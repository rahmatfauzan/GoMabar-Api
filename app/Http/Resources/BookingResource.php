<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\FieldResource;
use App\Http\Resources\UserResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'booked_status' => $this->status,
            'booking_date' => $this->booking_date ? $this->booking_date->format('Y-m-d') : null,
            'booked_slots' => $this->booked_slots, // Array jam
            'price' => $this->price,
            // 'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : $this->created_at->toDateTimeString(),

            // // Info Pemesan
            'customer_name' => $this->name_orders ?? $this->user->name,
            'customer_phone' => $this->phone_orders ?? $this->user->phone,
            'is_guest_order' => is_null($this->user_id),

            // Relasi (jika di-load)
            'user' => new UserResource($this->whenLoaded('user')),
            'field' => new FieldResource($this->whenLoaded('field')),
            'mabar_session' => new MabarSessionResource($this->whenLoaded('mabarSession')),
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
        ];
    }
}
