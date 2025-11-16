<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name_orders', 'phone_orders', 'invoice_number', 'status',
        'field_id', 'booking_date', 'booked_slots', 'price',
    ];

    protected $casts = [
        'booked_slots' => 'array',
        'booking_date' => 'date',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function field() { return $this->belongsTo(Field::class); }
    public function transaction() { return $this->hasOne(Transaction::class); }
    public function mabarSession() { return $this->hasOne(MabarSession::class); }
    // public function mabarSession() { return $this->belongsTo(MabarSession::class); } // Perbaikan: Booking MILIK MabarSession
}