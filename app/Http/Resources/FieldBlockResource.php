<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Data 'jadwal blokir' yang dikirim ke frontend
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'start_datetime' => $this->start_datetime, // Format "YYYY-MM-DD HH:MM:SS"
            'end_datetime' => $this->end_datetime,   // Format "YYYY-MM-DD HH:MM:SS"
        ];
    }
}