<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'destination_id',
        'name',
        'type',
        'price',
        'days',
        'nights',
        'min_pax',
        'valid_from',
        'valid_to',
        'generic_inclusions',
        'images',
        'is_active',
        'embedding',
    ];

    protected $casts = [
        'generic_inclusions' => 'array',
        'images' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function getIsShownAttribute(): bool
    {
        return (bool)$this->is_active;
    }

    public function setIsShownAttribute($value): void
    {
        $this->attributes['is_active'] = (bool)$value;
    }

    public function destination()
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }

    public function hotels()
    {
        return $this->belongsToMany(HotelModel::class, 'package_hotel', 'package_id', 'hotel_id');
    }

    public function activities()
    {
        return $this->belongsToMany(ActivityModel::class, 'package_activity', 'package_id', 'activity_id');
    }
}
