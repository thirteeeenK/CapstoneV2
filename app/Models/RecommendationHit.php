<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'session_token',
    'mode',
    'entity_type',
    'entity_id',
    'rank',
])]
class RecommendationHit extends Model
{
    public const MODE_AI = 'ai';

    public const MODE_DEFAULT = 'default';

    public const TYPE_IMPRESSION = 'impression';

    public const TYPE_HOTEL = 'hotel';

    public const TYPE_ACTIVITY = 'activity';

    // Catch-all exit: same-origin link/button outside the rec cards (sidebar,
    // burger, packages, nav). Never a HIT; makes exits visible as MISS.
    public const TYPE_NAV = 'nav';

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'rank' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
