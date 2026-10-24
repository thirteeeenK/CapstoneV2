<?php

namespace App\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'is_active', 'code_prefix', 'discount_type', 'percent_off', 'fixed_off_cents', 'max_discount_cents',
    'min_eligible_cents', 'issue_starts_at', 'issue_ends_at', 'redeem_starts_at', 'redeem_ends_at',
    'travel_starts_on', 'travel_ends_on', 'destination_ids', 'min_nights', 'max_nights', 'min_pax', 'max_pax',
    'total_redemption_limit', 'per_traveler_limit', 'codes_per_traveler_per_day', 'offer_valid_hours', 'award_weight',
    'terms', 'revision', 'created_by_admin_id', 'updated_by_admin_id',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /** Rule fields copied onto each issued offer; later campaign edits never change an issued offer. */
    public const TERM_FIELDS = [
        'discount_type', 'percent_off', 'fixed_off_cents', 'max_discount_cents', 'min_eligible_cents',
        'redeem_starts_at', 'redeem_ends_at', 'travel_starts_on', 'travel_ends_on', 'destination_ids',
        'min_nights', 'max_nights', 'min_pax', 'max_pax', 'total_redemption_limit', 'per_traveler_limit', 'terms',
    ];

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'percent_off' => 'integer', 'fixed_off_cents' => 'integer', 'max_discount_cents' => 'integer',
            'min_eligible_cents' => 'integer', 'issue_starts_at' => 'datetime', 'issue_ends_at' => 'datetime',
            'redeem_starts_at' => 'datetime', 'redeem_ends_at' => 'datetime', 'travel_starts_on' => 'date', 'travel_ends_on' => 'date',
            'destination_ids' => 'array', 'min_nights' => 'integer', 'max_nights' => 'integer', 'min_pax' => 'integer', 'max_pax' => 'integer',
            'total_redemption_limit' => 'integer', 'per_traveler_limit' => 'integer', 'codes_per_traveler_per_day' => 'integer',
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
     * The campaign rules as they stand now, for snapshotting onto an issued offer.
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
