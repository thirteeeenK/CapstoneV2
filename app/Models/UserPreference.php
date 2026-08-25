<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'destination',
    'traveler_type',
    'vibes',
    'amenities',
    'activities',
    'notes',
])]
class UserPreference extends Model
{
    protected $casts = [
        'vibes' => 'array',
        'amenities' => 'array',
        'activities' => 'array',
        'recommendation_explanations' => 'array',
    ];
}
