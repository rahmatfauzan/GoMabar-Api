<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldOperatingHours extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'is_open',
        'day_of_week',
        'start_time',
        'end_time',
    ];
    protected $casts = [
        'is_open' => 'boolean', // <-- TAMBAHKAN
    ];
    public function field()
    {
        return $this->belongsTo(Field::class);
    }
}
