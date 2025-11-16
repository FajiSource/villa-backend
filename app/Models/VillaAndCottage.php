<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VillaAndCottage extends Model
{
    use HasFactory;

    protected $table = 'villas_and_cottages';

    protected $fillable = [
        'type',
        'name',
        'description',
        'image',
        'amenities',
        'capacity',
        'price_per_night',
        'status',
    ];

    protected $casts = [
        'price_per_night' => 'decimal:2',
        'amenities' => 'array',
    ];

    /**
     * Get the bookings for this villa/cottage.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'rc_id');
    }
}

