<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MabarParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'mabar_session_id',
        'user_id',
        'guest_name',
        'payment_proof_image',
        'status',
    ];

    public function mabarSession()
    {
        return $this->belongsTo(MabarSession::class, 'mabar_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class); // Partisipan (jika bukan guest)
    }

    public function sessionParticipants()
    {
        return $this->hasMany(MabarParticipant::class, 'mabar_session_id', 'mabar_session_id');
    }
}
