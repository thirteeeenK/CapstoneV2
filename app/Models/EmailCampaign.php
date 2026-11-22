<?php

namespace App\Models;

use Database\Factories\EmailCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * An admin promotional email and who it goes to. Manual and scheduled
 * campaigns send once; automatic ones send each time their trigger fires.
 */
#[Fillable([
    'name', 'subject', 'preheader', 'headline', 'body', 'image_path',
    'cta_label', 'cta_target', 'cta_target_id', 'cta_path',
    'send_mode', 'trigger', 'audience', 'promotion_id', 'status', 'scheduled_at', 'activated_at',
    'created_by_admin_id', 'updated_by_admin_id',
])]
class EmailCampaign extends Model
{
    /** @use HasFactory<EmailCampaignFactory> */
    use HasFactory;

    public const MODE_MANUAL = 'manual';

    public const MODE_SCHEDULED = 'scheduled';

    public const MODE_AUTOMATIC = 'automatic';

    public const MODES = [self::MODE_MANUAL, self::MODE_SCHEDULED, self::MODE_AUTOMATIC];

    public const TRIGGER_PROMOTION_LIVE = 'promotion_live';

    public const TRIGGER_PACKAGE_PUBLISHED = 'package_published';

    /** @var array<string, string> */
    public const TRIGGERS = [
        self::TRIGGER_PROMOTION_LIVE => 'A Lucky promotion goes live',
        self::TRIGGER_PACKAGE_PUBLISHED => 'A new tour package is published',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var array<string, string> where the button leads */
    public const CTA_TARGETS = [
        'lucky' => 'I’m Feeling Lucky page',
        'destination' => 'A destination',
        'package' => 'A tour package',
        'packages' => 'All tour packages',
        'trip_planner' => 'Trip Planner',
        'custom' => 'Another SunnyTrips page',
    ];

    /** @var array<string, string> */
    public const SEGMENTS = [
        'all' => 'Everyone who opted in',
        'past_travelers' => 'Past travelers (a completed trip)',
        'never_booked' => 'Never booked',
        'inactive' => 'No booking request recently',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'scheduled_at' => 'datetime',
            'activated_at' => 'datetime',
            'cta_target_id' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(EmailCampaignRun::class);
    }

    public function recipients(): HasManyThrough
    {
        return $this->hasManyThrough(EmailCampaignRecipient::class, EmailCampaignRun::class);
    }

    public function isAutomatic(): bool
    {
        return $this->send_mode === self::MODE_AUTOMATIC;
    }

    /**
     * Whether its content, audience and mode can still be changed: a one-off
     * campaign only before it starts sending; an automatic one any time.
     */
    public function isEditable(): bool
    {
        return $this->isAutomatic() || in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_SENDING => 'Sending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_ACTIVE => 'Automatic · on',
            self::STATUS_PAUSED => 'Automatic · paused',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function modeLabel(): string
    {
        return match ($this->send_mode) {
            self::MODE_SCHEDULED => 'Scheduled',
            self::MODE_AUTOMATIC => 'Automatic: '.(self::TRIGGERS[$this->trigger] ?? 'trigger'),
            default => 'Manual',
        };
    }

    /**
     * @return array{segment: string, destinations: array<int, string>, traveler_types: array<int, string>, inactive_days: int}
     */
    public function audienceFilters(): array
    {
        $audience = $this->audience ?? [];

        return [
            'segment' => array_key_exists($audience['segment'] ?? '', self::SEGMENTS) ? $audience['segment'] : 'all',
            'destinations' => array_values(array_filter((array) ($audience['destinations'] ?? []), 'is_string')),
            'traveler_types' => array_values(array_filter((array) ($audience['traveler_types'] ?? []), 'is_string')),
            'inactive_days' => max(1, (int) ($audience['inactive_days'] ?? 90)),
        ];
    }
}
