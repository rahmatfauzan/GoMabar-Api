<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\SportCategoryResource; // <-- Import

class FieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'field_photo' => $this->field_photo ? url('storage/' . $this->field_photo) : null, // (Contoh URL)
            'price_weekday' => $this->price_weekday,
            'price_weekend' => $this->price_weekend,
            // Sertakan kategori jika di-load
            'sport_category' => new SportCategoryResource($this->whenLoaded('sportCategory')),
            // Sertakan jam operasional jika di-load
            'operating_hours' => FieldOperatingHoursResource::collection($this->whenLoaded('operatingHours')),
        ];
    }
}