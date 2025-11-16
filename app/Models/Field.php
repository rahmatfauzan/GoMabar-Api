<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport_category_id',
        'name',
        'description',
        'field_photo',
        'price_weekday',
        'price_weekend',
        'status',
    ];

    public function sportCategory()
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function operatingHours()
    {
        return $this->hasMany(FieldOperatingHours::class);
    }

    public function blocks()
    {
        return $this->hasMany(FieldBlock::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}