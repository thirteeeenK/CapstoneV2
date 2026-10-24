<?php

namespace App\Models;

use Database\Factories\PromotionRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promotion_id', 'lucky_offer_id', 'booking_id', 'user_id', 'code', 'status', 'eligible_cents', 'discount_cents',
    'allocations', 'terms', 'status_reason', 'reserved_at', 'redeemed_at', 'released_at',
])]
class PromotionRedemption extends Model
{
    /** @use HasFactory<PromotionRedemptionFactory> */
    use HasFactory;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_RELEASED = 'released';

    /** Redemptions that use up campaign capacity. */
    public const COUNTED = [self::STATUS_RESERVED, self::STATUS_REDEEMED];

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'eligible_cents' => 'integer', 'discount_cents' => 'integer', 'allocations' => 'array', 'terms' => 'array',
            'reserved_at' => 'datetime', 'redeemed_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(LuckyOffer::class, 'lucky_offer_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
