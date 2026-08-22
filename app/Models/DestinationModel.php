<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationModel extends Model
{
    use HasFactory;

    protected $table = 'destinations';

    protected $fillable = [
        'name',
        'region',
        'description',
        'image',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function hotels()
    {
        return $this->hasMany(HotelModel::class, 'destination_id');
    }

    public function activities()
    {
        return $this->hasMany(ActivityModel::class, 'destination_id');
    }

    public function packages()
    {
        return $this->hasMany(Package::class, 'destination_id');
    }
}
