<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'booking_status_history';

    protected $fillable = [
        'booking_id',
        'from_status',
        'to_status',
        'note',
        'actor_type',
        'actor_id',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
