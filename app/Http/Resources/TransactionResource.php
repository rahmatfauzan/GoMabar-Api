<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'payment_gateway' => $this->payment_gateway,
            'created_at' => $this->created_at->toDateTimeString(),
            // Jangan tampilkan 'gateway_token' ke frontend
        ];
    }
}