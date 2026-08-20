<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'phone_number', 'address', 'password',
    'chatbot_flag_count', 'ban_reason', 'ban_level', 'banned_at', 'ban_expires_at',
    'terms_accepted_at', 'privacy_accepted_at', 'ai_disclosure_accepted_at',
    'terms_version', 'privacy_version', 'ai_disclosure_version',
    'consent_ip_address', 'consent_user_agent',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const BAN_LEVEL_WARNING = 'warning';

    public const BAN_LEVEL_TEMPORARY = 'temporary';

    public const BAN_LEVEL_PERMANENT = 'permanent';

    // /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'chatbot_flag_count' => 'integer',
            'banned_at' => 'datetime',
            'ban_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'ai_disclosure_accepted_at' => 'datetime',
        ];
    }

    /**
     * A warning is a recorded notice only; it does not restrict access.
     */
    public function isWarned(): bool
    {
        return $this->ban_level === self::BAN_LEVEL_WARNING;
    }

    /**
     * A permanent ban has no expiry and always restricts access.
     */
    public function isPermanentlyBanned(): bool
    {
        return $this->ban_level === self::BAN_LEVEL_PERMANENT;
    }

    /**
     * A temporary ban restricts access only while its expiry is in the future.
     * Once expired it no longer counts, without any manual unban step.
     */
    public function isTemporarilyBanned(): bool
    {
        return $this->ban_level === self::BAN_LEVEL_TEMPORARY
            && $this->ban_expires_at !== null
            && $this->ban_expires_at->isFuture();
    }

    /**
     * Whether the account is actively banned (login blocked, sessions killed).
     */
    public function isBanned(): bool
    {
        return $this->isPermanentlyBanned() || $this->isTemporarilyBanned();
    }

    /**
     * Short status label for admin badges: active, warned, temporary, permanent.
     */
    public function banStatus(): string
    {
        if ($this->isPermanentlyBanned()) {
            return 'permanent';
        }

        if ($this->isTemporarilyBanned()) {
            return 'temporary';
        }

        if ($this->isWarned()) {
            return 'warned';
        }

        return 'active';
    }

    /**
     * Human-readable enforcement message for login rejection / session logout.
     */
    public function banStatusMessage(): string
    {
        if ($this->isTemporarilyBanned()) {
            return 'Your account is temporarily suspended. Access is restored after '
                .$this->ban_expires_at->format('M d, Y')
                .'. Reason: '.($this->ban_reason ?? 'Terms of service violation.');
        }

        return 'Your account has been permanently banned. Reason: '.($this->ban_reason ?? 'Terms of service violation.');
    }

    /**
     * Structured ban notice flashed to the suspended page (message + level + dates).
     *
     * @return array<string, string|null>
     */
    public function banNotice(): array
    {
        return [
            'level' => $this->isPermanentlyBanned() ? self::BAN_LEVEL_PERMANENT : self::BAN_LEVEL_TEMPORARY,
            'reason' => $this->ban_reason,
            'banned_at' => $this->banned_at?->format('M d, Y'),
            'expires_at' => $this->ban_expires_at?->format('M d, Y'),
            'message' => $this->banStatusMessage(),
        ];
    }

    public function abuseReports()
    {
        return $this->hasMany(ChatbotAbuseReport::class, 'user_id');
    }

    /**
     * The structured onboarding preference data persisted alongside the embedding.
     */
    public function userPreference()
    {
        return $this->hasOne(UserPreference::class, 'user_id');
    }

    /**
     * Scope for users with an active ban (permanent or a not-yet-expired temporary ban).
     */
    public function scopeActiveBan($query)
    {
        return $query->where(function ($q) {
            $q->where('ban_level', self::BAN_LEVEL_PERMANENT)
                ->orWhere(function ($q) {
                    $q->where('ban_level', self::BAN_LEVEL_TEMPORARY)
                        ->where('ban_expires_at', '>', now());
                });
        });
    }
}
