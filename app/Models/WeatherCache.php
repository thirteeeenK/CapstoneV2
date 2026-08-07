<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherCache extends Model
{
    protected $table = 'weather_cache';

    protected $fillable = [
        'cache_key',
        'weather_data',
        'fetched_at',
        'expires_at',
    ];

    protected $casts = [
        'weather_data' => 'array',
        'fetched_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
