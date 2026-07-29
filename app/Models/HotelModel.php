<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RoomType;

class HotelModel extends Model
{
    use HasFactory;

    protected $table = 'hotels';

    protected $fillable = [
        'hotel_name',
        'destination_id',
        'type',
        'vibe_tags',
        'featured_amenities',
        'hotel_description',
        'specific_address',
        'latitude',
        'longitude',
        'images',
        'embedding',
        'is_shown'
    ];

    // I-cast natin ang embedding para maging array sa PHP side
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'vibe_tags' => 'array',
        'featured_amenities' => 'array',
        'images' => 'array',
        'is_shown' => 'boolean'
    ];

    public function rooms()
    {
        return $this->hasMany(RoomType::class, 'hotel_id');
    }

    public function destination()
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }
}