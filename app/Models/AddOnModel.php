<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddOnModel extends Model
{
    use HasFactory;

    protected $table = 'add_ons';

    protected $fillable = [
        'destination_id',
        'name',
        'type',
        'description',
        'inclusions',
        'pricing_tiers',
        'surcharges',
        'is_shown',
        'embedding',
    ];

    protected $casts = [
        'inclusions' => 'array',
        'pricing_tiers' => 'array',
        'surcharges' => 'array',
        'is_shown' => 'boolean',
    ];

    public function destination()
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }
}
