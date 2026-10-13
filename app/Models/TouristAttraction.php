<?php

namespace App\Models;

use Database\Factories\TouristAttractionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['destination_id', 'name', 'description', 'latitude', 'longitude', 'scheduling', 'is_shown'])]
class TouristAttraction extends Model
{
    /** @use HasFactory<TouristAttractionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['scheduling' => 'array', 'is_shown' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationModel::class);
    }
}
