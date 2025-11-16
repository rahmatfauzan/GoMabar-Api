<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MabarIndexResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'host_user_id' => $this->host_user_id,
            'booking_id' => $this->booking_id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? url('storage/' . $this->cover_image) : null,
            'slots_total' => $this->slots_total,
            'price_per_slot' => $this->price_per_slot,
            'payment_instructions' => $this->payment_instructions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'participants_count' => $this->participants_count,
            
            // Transforming the 'host' relationship
            'host' => [
                'id' => $this->host->id,
                'name' => $this->host->name,
                'email' => $this->host->email,
                'phone' => $this->host->phone,
                'address' => $this->host->address,
            ],

            // Transforming the 'booking' relationship
            'booking' => [
                'id' => $this->booking->id,
                'field_id' => $this->booking->field_id,
                'booking_date' => $this->booking->booking_date ? $this->booking->booking_date->toDateString() : null,
                'booked_slots' => $this->booking->booked_slots,
                'booked_status' => $this->booking->status,
                'transaction_id' => $this->booking->transaction,

                
                'field' => [
                    'id' => $this->booking->field->id,
                    'name' => $this->booking->field->name,
                    'sport_category_id' => $this->booking->field->sport_category_id,
                    'sport_category' => [
                        'id' => $this->booking->field->sportCategory->id,
                        'name' => $this->booking->field->sportCategory->name,
                        'icon' => $this->booking->field->sportCategory->icon,
                    ],
                ],
            ],
        ];
    }
}
