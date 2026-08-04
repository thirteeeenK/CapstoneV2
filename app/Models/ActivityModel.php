<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityModel extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $fillable = [
        'destination_id',
        'activity_name',
        'description',
        'category',
        'activity_level',
        'duration',
        'capacity',
        'requirements',
        'vibe_tags',
        'ideal_for',
        'rate',
        'inclusions',
        'exclusions',
        'itinerary',
        'notes',
        'images',
        'embedding',
        'is_shown'
    ];

    protected $casts = [
        'vibe_tags' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'itinerary' => 'array',
        'images' => 'array',
        'is_shown' => 'boolean'
    ];

    public function destination()
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_activity', 'activity_id', 'package_id');
    }
}
        