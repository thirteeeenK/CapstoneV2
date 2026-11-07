<?php

namespace App\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'name', 'is_active', 'win_percent', 'discount_type', 'percent_off', 'fixed_off_cents', 'max_discount_cents',
    'min_eligible_cents', 'redeem_starts_at', 'redeem_ends_at', 'duration_days',
    'travel_starts_on', 'travel_ends_on', 'destination_ids', 'min_nights', 'max_nights', 'min_pax', 'max_pax',
    'total_redemption_limit', 'per_traveler_limit', 'offer_valid_hours', 'award_weight',
    'terms', 'revision', 'created_by_admin_id', 'updated_by_admin_id',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const DEFAULT_WIN_PERCENT = 80;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LIMIT_REACHED = 'limit_reached';

    /** Rule fields copied onto each won offer; later campaign edits never change a discount already won. */
    public const TERM_FIELDS = [
        'discount_type', 'percent_off', 'fixed_off_cents', 'max_discount_cents', 'min_eligible_cents',
        'redeem_starts_at', 'redeem_ends_at', 'duration_days', 'travel_starts_on', 'travel_ends_on', 'destination_ids',
        'min_nights', 'max_nights', 'min_pax', 'max_pax', 'total_redemption_limit', 'per_traveler_limit', 'terms',
    ];

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'win_percent' => 'integer', 'percent_off' => 'integer', 'fixed_off_cents' => 'integer', 'max_discount_cents' => 'integer',
            'min_eligible_cents' => 'integer', 'redeem_starts_at' => 'datetime', 'redeem_ends_at' => 'datetime', 'duration_days' => 'integer',
            'travel_starts_on' => 'date', 'travel_ends_on' => 'date',
            'destination_ids' => 'array', 'min_nights' => 'integer', 'max_nights' => 'integer', 'min_pax' => 'integer', 'max_pax' => 'integer',
            'total_redemption_limit' => 'integer', 'per_traveler_limit' => 'integer',
            'offer_valid_hours' => 'integer', 'award_weight' => 'integer', 'revision' => 'integer',
        ];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LuckyOffer::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    /**
     * When the campaign stops awarding discounts: the expiration date or the
     * end of the duration, whichever comes first. Duration counts from the start date,
     * or from the campaign's creation when it has none.
     */
    public function expiresAt(): ?Carbon
    {
        $durationEnd = $this->duration_days ? ($this->redeem_starts_at ?? $this->created_at)?->copy()->addDays($this->duration_days) : null;
        if (! $durationEnd || ! $this->redeem_ends_at) {
            return $durationEnd ?? $this->redeem_ends_at;
        }

        return $durationEnd->lt($this->redeem_ends_at) ? $durationEnd : $this->redeem_ends_at;
    }

    /**
     * Redemption counts to eager load for the usage indicators.
     *
     * @return array<string, \Closure>
     */
    public static function usageCounts(): array
    {
        return [
            'redemptions as used_count' => fn ($query) => $query->whereIn('status', PromotionRedemption::COUNTED),
            'redemptions as reserved_count' => fn ($query) => $query->where('status', PromotionRedemption::STATUS_RESERVED),
        ];
    }

    /**
     * Redemptions holding a slot, paid or awaiting payment.
     */
    public function usedRedemptions(): int
    {
        return (int) ($this->used_count ?? $this->redemptions()->whereIn('status', PromotionRedemption::COUNTED)->count());
    }

    /**
     * Slots left, or null when the campaign has no limit.
     */
    public function remainingRedemptions(): ?int
    {
        return $this->total_redemption_limit === null ? null : max(0, $this->total_redemption_limit - $this->usedRedemptions());
    }

    public function status(): string
    {
        return match (true) {
            (bool) $this->expiresAt()?->isPast() => self::STATUS_EXPIRED,
            $this->remainingRedemptions() === 0 => self::STATUS_LIMIT_REACHED,
            ! $this->is_active => self::STATUS_PAUSED,
            (bool) $this->redeem_starts_at?->isFuture() => self::STATUS_SCHEDULED,
            default => self::STATUS_ACTIVE,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status()) {
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_LIMIT_REACHED => 'Limit reached',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_SCHEDULED => 'Scheduled',
            default => 'Active',
        };
    }

    /**
     * Redemption progress for admins, e.g. "12 of 100 users redeemed · 88 left · 3 awaiting payment".
     */
    public function usageSummary(): string
    {
        $used = $this->usedRedemptions();
        $summary = $this->total_redemption_limit === null
            ? $used.' '.($used === 1 ? 'user' : 'users').' redeemed · no limit'
            : $used.' of '.$this->total_redemption_limit.' users redeemed · '.$this->remainingRedemptions().' left';
        $awaitingPayment = (int) ($this->reserved_count ?? $this->redemptions()->where('status', PromotionRedemption::STATUS_RESERVED)->count());

        return $summary.($awaitingPayment > 0 ? ' · '.$awaitingPayment.' awaiting payment' : '');
    }

    /**
     * The campaign rules as they stand now, for snapshotting onto a won offer.
     *
     * @return array<string, mixed>
     */
    public function termsSnapshot(): array
    {
        $terms = [];
        foreach (self::TERM_FIELDS as $field) {
            $value = $this->{$field};
            $terms[$field] = $value instanceof \DateTimeInterface
                ? (in_array($field, ['travel_starts_on', 'travel_ends_on'], true) ? $value->format('Y-m-d') : $value->format(DATE_ATOM))
                : $value;
        }

        return $terms + ['name' => $this->name, 'revision' => $this->revision];
    }

    public function discountLabel(): string
    {
        return self::describeDiscount($this->termsSnapshot());
    }

    /**
     * @param  array<string, mixed>  $terms
     */
    public static function describeDiscount(array $terms): string
    {
        if ($terms['discount_type'] === self::TYPE_PERCENT) {
            return $terms['percent_off'].'% off'.($terms['max_discount_cents'] ? ' (up to ₱'.number_format($terms['max_discount_cents'] / 100, 2).')' : '');
        }

        return '₱'.number_format(($terms['fixed_off_cents'] ?? 0) / 100, 2).' off';
    }
}
