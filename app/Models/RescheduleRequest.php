<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Booking;
use App\Models\User;

class RescheduleRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'new_check_in',
        'new_check_out',
        'reason',
        'status',
        'responded_at',
        'responded_by',
    ];

    protected $casts = [
        'new_check_in' => 'datetime',
        'new_check_out' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /**
     * Get the booking that this reschedule request belongs to.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the admin who responded to this request.
     */
    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}

