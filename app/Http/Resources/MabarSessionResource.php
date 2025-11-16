<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\MabarParticipantResource;
use App\Http\Resources\BookingResource;

class MabarSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? url('storage/' . $this->cover_image) : null,
            'slots_total' => $this->slots_total,
            'price_per_slot' => $this->price_per_slot,
            'payment_instructions' => $this->payment_instructions,
            'participants_count' => $this->participants_count,
            'sport_category' => [
                'name' => $this->booking->field->sportCategory->name,
                'icon' => $this->booking->field->sportCategory->icon,
            ],

            // Relasi yang di-load di index (host & booking)
            'host' => new UserResource($this->whenLoaded('host')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'fieldName' => $this->booking->field->name,
            // 'participants' HANYA akan ada jika 'participants' di-load
            'participants' => $this->whenLoaded('participants', function () {
                // Logika ini HANYA berjalan jika participants di-load
                return MabarParticipantResource::collection($this->participants);
            }),
        ];
    }
}
