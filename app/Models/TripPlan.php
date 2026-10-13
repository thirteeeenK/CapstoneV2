<?php

namespace App\Models;

use Database\Factories\TripPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'destination_id', 'title', 'start_date', 'end_date', 'pax', 'mode', 'revision'])]
class TripPlan extends Model
{
    /** @use HasFactory<TripPlanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'pax' => 'integer', 'revision' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TripPlanItem::class)->orderBy('id');
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(TripItinerary::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
