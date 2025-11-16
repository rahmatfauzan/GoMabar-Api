<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldOperatingHoursResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Data 'jam operasional' yang dikirim ke frontend
        return [
            'day_of_week' => $this->day_of_week, // 1=Senin, 7=Minggu
            'start_time' => $this->start_time,   // Format "HH:MM:SS"
            'end_time' => $this->end_time,     // Format "HH:MM:SS"
            'is_open' => $this->is_open, // Default-nya Buka
        ];
    }
}