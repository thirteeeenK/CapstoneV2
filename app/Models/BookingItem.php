<?php

namespace App\Models;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $table = 'booking_items';

    public const AVAIL_PENDING = 'pending';
    public const AVAIL_AVAILABLE = 'available';
    public const AVAIL_UNAVAILABLE = 'unavailable';

    protected $fillable = [
        'booking_id',
        'item_type',
        'item_id',
        'item_title',
        'item_subtitle',
        'hotel_name',
        'unit_price',
        'quantity',
        'selected_pax',
        'check_in_date',
        'check_out_date',
        'nights',
        'subtotal',
        'item_snapshot',
        'availability_status',
        'admin_note',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'item_snapshot' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function itemable()
    {
        return $this->morphTo('itemable', 'item_type', 'item_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
