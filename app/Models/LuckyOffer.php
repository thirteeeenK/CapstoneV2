<?php

namespace App\Models;

use Database\Factories\LuckyOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'uuid', 'user_id', 'promotion_id', 'code', 'promotion_revision', 'terms', 'destination_id', 'room_id', 'activity_ids',
    'nights', 'pax', 'check_in_date', 'check_out_date', 'max_budget', 'budget_exceeded', 'quoted_total_cents',
    'estimated_discount_cents', 'selection_signature', 'lucky_group_id', 'accepted_at', 'expires_at',
])]
class LuckyOffer extends Model
{
    /** @use HasFactory<LuckyOfferFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'terms' => 'array', 'activity_ids' => 'array', 'nights' => 'integer', 'pax' => 'integer', 'room_id' => 'integer',
            'check_in_date' => 'date', 'check_out_date' => 'date', 'max_budget' => 'decimal:2', 'budget_exceeded' => 'boolean',
            'quoted_total_cents' => 'integer', 'estimated_discount_cents' => 'integer', 'promotion_revision' => 'integer',
            'accepted_at' => 'datetime', 'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(PromotionRedemption::class);
    }

    public function hasCode(): bool
    {
        return $this->code !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
