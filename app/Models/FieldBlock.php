<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'start_datetime',
        'end_datetime',
        'reason',
    ];

    public function field()
    {
        return $this->belongsTo(Field::class);
    }
}