<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DestinationModel extends Model
{
    protected $table = 'destinations';
    protected $fillable = [
        'name'
    ];

    public function hotels()
    {
        return $this->hasMany(HotelModel::class, 'destination_id');
    }

    public function activities()
    {
        return $this->hasMany(ActivityModel::class, 'destination_id');
    }
}
