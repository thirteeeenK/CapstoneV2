<?php

namespace App\Models;

use Database\Factories\TripItineraryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_plan_id', 'revision', 'snapshot', 'transferred_at'])]
class TripItinerary extends Model
{
    /** @use HasFactory<TripItineraryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'transferred_at' => 'datetime', 'revision' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TripPlan::class, 'trip_plan_id');
    }
}
