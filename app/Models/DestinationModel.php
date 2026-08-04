<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationModel extends Model
{
    protected $table = 'destinations';
    protected $fillable = [
        'name',
        'description',
        'image',
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
