<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
// Hapus: use App\Http\Resources\RoleResource; // Tidak diperlukan lagi

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'image' => $this->image,
            
            // --- PERBAIKAN: MENGGUNAKAN ANONYMOUS RESOURCE COLLECTION ---
            'roles' => $this->whenLoaded('roles', function () {
                // Return Resource Collection yang dibuat di tempat
                return JsonResource::collection($this->roles)->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                });
            }),
            // -------------------------------------------------------------
        ];
    }
}