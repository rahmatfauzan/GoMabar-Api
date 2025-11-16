<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MabarSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'host_user_id',
        'booking_id',
        'title',
        'type',
        'description',
        'cover_image',
        'slots_total',
        'price_per_slot',
        'payment_instructions',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    } // Satu Sesi Mabar milik satu Booking
    public function participants()
    {
        return $this->hasMany(MabarParticipant::class, 'mabar_session_id');
    }
    
}
