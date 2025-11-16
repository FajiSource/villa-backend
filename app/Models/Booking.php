<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rc_id',
        'name',
        'contact',
        'check_in',
        'check_out',
        'pax',
        'special_req',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    /**
     * Get the user that owns the booking.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the villa/cottage for this booking.
     */
    public function villaAndCottage()
    {
        return $this->belongsTo(VillaAndCottage::class, 'rc_id');
    }
}

