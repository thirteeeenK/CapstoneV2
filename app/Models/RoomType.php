<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\HotelModel;

class RoomType extends Model
{
    protected $table = 'rooms';

    protected $fillable = [
        'hotel_id',
        'room_name',
        'ideal_guest',
        'ideal_for',
        'total_rooms',
        'total_number_of_rooms',
        'occupancy',
        'bed_configuration',
        'room_size',
        'base_price',
        'description',
        'view_type',
        'room_amenities',
        'additional_notes',
        'images',
        'embedding',
        'is_shown'
    ];

    protected $casts = [
        'room_amenities' => 'array',
        'images' => 'array',
        'is_shown' => 'boolean'
    ];

    public function getIdealGuestAttribute($value)
    {
        return $value ?? ($this->attributes['ideal_for'] ?? null);
    }

    public function getTotalNumberOfRoomsAttribute($value)
    {
        return $value ?? ($this->attributes['total_rooms'] ?? null);
    }

    public function hotel()
    {
        return $this->belongsTo(HotelModel::class, 'hotel_id');
    }
}