<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'hotel_id',
        'room_name',
        'ideal_guest',
        'ideal_for',
        'total_rooms',
        'total_number_of_rooms',
        'occupancy',
        'base_occupancy',
        'max_occupancy',
        'bed_configuration',
        'room_size',
        'base_price',
        'extra_person_fee',
        'description',
        'view_type',
        'room_amenities',
        'additional_notes',
        'images',
        'embedding',
        'is_shown',
    ];

    protected $casts = [
        'room_amenities' => 'array',
        'images' => 'array',
        'is_shown' => 'boolean',
        'base_price' => 'decimal:2',
        'extra_person_fee' => 'decimal:2',
    ];

    public function getBaseOccupancyAttribute($value)
    {
        return (int) ($value ?: 2);
    }

    public function getMaxOccupancyAttribute($value)
    {
        return (int) ($value ?: ($this->attributes['occupancy'] ?? 2));
    }

    public function getIdealGuestAttribute($value)
    {
        return $value ?? ($this->attributes['ideal_for'] ?? null);
    }

    public function getTotalNumberOfRoomsAttribute($value)
    {
        return $value ?? ($this->attributes['total_rooms'] ?? null);
    }

    public function getRatePerNightAttribute()
    {
        return $this->attributes['base_price'] ?? null;
    }

    /**
     * Calculate nightly rate dynamically based on selected pax.
     */
    public function calculateNightlyRate(int $selectedPax = 2): float
    {
        $basePrice = (float) $this->base_price;
        $basePax = $this->base_occupancy;
        $extraFee = (float) ($this->extra_person_fee ?: 0.00);

        if ($selectedPax > $basePax) {
            $extraGuests = $selectedPax - $basePax;

            return $basePrice + ($extraGuests * $extraFee);
        }

        return $basePrice;
    }

    public function hotel()
    {
        return $this->belongsTo(HotelModel::class, 'hotel_id');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_hotel', 'room_type_id', 'package_id')
            ->withPivot('hotel_id')
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable', 'reviewable_type', 'reviewable_id');
    }

    public function reviewSummary()
    {
        return $this->morphOne(ReviewSummary::class, 'summarizable', 'summarizable_type', 'summarizable_id');
    }
}
