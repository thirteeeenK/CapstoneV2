<?php

namespace App\Models;

use Database\Factories\TripPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_plan_id', 'item_type', 'item_id', 'options'])]
class TripPlanItem extends Model
{
    /** @use HasFactory<TripPlanItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['options' => 'array'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TripPlan::class, 'trip_plan_id');
    }
}
