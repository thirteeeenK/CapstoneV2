<?php

namespace App\Models;

use App\Services\Payment\PaymentGatewayException;
use App\Services\PromotionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $table = 'bookings';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

    public const STATUS_CANCELLATION_DENIED = 'cancellation_denied';

    public const HOLD_STATUSES = ['pending', 'approved', 'paid', 'cancellation_requested', 'cancellation_denied'];

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partial';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'payment_plan_version', 'payment_plan', 'payment_environment', 'payment_policy',
        'deposit_required_cents', 'deposit_secured_at', 'balance_due_at', 'balance_overdue_at',
        'booking_code',
        'user_id',
        'status',
        'approved_at',
        'payment_deadline',
        'paid_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'cancellation_reason',
        'cancellation_request_reason',
        'cancellation_requested_at',
        'cancellation_requested_from',
        'expired_at',
        'admin_notes',
        'reviewed_by_admin_id',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'admin_discount_amount',
        'admin_surcharge_amount',
        'price_adjustment_reason',
        'price_adjusted_at',
        'net_amount',
        'payment_status',
        'payment_method',
        'payment_reference',
        'gateway',
        'gateway_reference',
        'payment_url',
        'gateway_data',
        'contact_name',
        'contact_email',
        'contact_phone',
        'special_requests',
        'guest_manifest',
        'booked_by_admin_id',
        'booking_source',
        'is_walk_in',
        'trip_itineraries',
        'trip_end_override',
        'rebooked_from_booking_id',
        'promotion_discount_amount',
    ];

    protected $casts = [
        'payment_plan_version' => 'integer', 'payment_policy' => 'array', 'deposit_required_cents' => 'integer',
        'deposit_secured_at' => 'datetime', 'balance_due_at' => 'datetime', 'balance_overdue_at' => 'datetime',
        'trip_itineraries' => 'array',
        'trip_end_override' => 'datetime',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'admin_discount_amount' => 'decimal:2',
        'admin_surcharge_amount' => 'decimal:2',
        'promotion_discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'guest_manifest' => 'array',
        'gateway_data' => 'array',
        'is_walk_in' => 'boolean',
        'approved_at' => 'datetime',
        'payment_deadline' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'expired_at' => 'datetime',
        'price_adjusted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(AdminModel::class, 'reviewed_by_admin_id');
    }

    public function bookedByAdmin()
    {
        return $this->belongsTo(AdminModel::class, 'booked_by_admin_id');
    }

    /**
     * The earlier booking this request repeats, recorded for audit only.
     */
    public function rebookedFrom()
    {
        return $this->belongsTo(self::class, 'rebooked_from_booking_id');
    }

    /**
     * Whether a cancellation request on this booking was reviewed and denied.
     */
    public function hasDeniedCancellationRequest(): bool
    {
        return $this->status === self::STATUS_CANCELLATION_DENIED
            || ($this->cancellation_requested_at !== null && $this->cancellation_reason !== null
                && ! in_array($this->status, [self::STATUS_CANCELLATION_REQUESTED, self::STATUS_CANCELLED], true));
    }

    public function isAgentBooked(): bool
    {
        return $this->booked_by_admin_id !== null;
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function history()
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('id', 'desc');
    }

    public function attachments()
    {
        return $this->hasMany(BookingAttachment::class)->orderBy('id', 'desc');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(BookingPaymentTransaction::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class)->orderByDesc('id');
    }

    public function promotionRedemption(): HasOne
    {
        return $this->hasOne(PromotionRedemption::class)->latestOfMany();
    }

    /**
     * Whether this booking currently holds inventory.
     */
    public function isHoldStatus(): bool
    {
        return in_array($this->status, self::HOLD_STATUSES, true);
    }

    /**
     * Whether the approved payment window has passed.
     */
    public function isPaymentDeadlinePassed(): bool
    {
        if ($this->payment_plan_version === 2) {
            $deadline = $this->deposit_secured_at ? $this->balance_due_at : $this->payment_deadline;

            return $this->status === self::STATUS_APPROVED && $deadline && now()->greaterThan($deadline);
        }

        return $this->status === self::STATUS_APPROVED
            && $this->payment_deadline
            && now()->greaterThan($this->payment_deadline);
    }

    /**
     * Transition status with an audit trail entry.
     */
    public function markStatus(string $to, ?string $note = null, $actor = null): void
    {
        $from = $this->status;
        $this->status = $to;
        $this->save();

        if ($actor === null && Auth::check()) {
            $actor = Auth::user();
        }

        BookingStatusHistory::create([
            'booking_id' => $this->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'actor_type' => $actor ? get_class($actor) : null,
            'actor_id' => $actor?->getKey(),
        ]);
        $this->syncPromotionRedemption($to);
    }

    /**
     * Atomically transition the status from one of $expectedFrom to $to.
     * The update only affects rows still in an expected state, so concurrent
     * or repeated callers cannot double-apply a transition; the audit entry
     * is written only when the transition actually happened.
     *
     * @return bool true when this call performed the transition
     */
    public function transitionTo(string $to, array $expectedFrom, array $data = [], ?string $note = null, $actor = null): bool
    {
        $from = $this->status;

        $affected = DB::transaction(function () use ($to, $expectedFrom, $data) {
            return static::query()
                ->whereKey($this->getKey())
                ->whereIn('status', $expectedFrom)
                ->update(array_merge(['status' => $to], $data));
        });

        if ($affected === 0) {
            return false;
        }

        $this->status = $to;
        foreach ($data as $key => $value) {
            $this->{$key} = $value;
        }

        if ($actor === null && Auth::check()) {
            $actor = Auth::user();
        }

        BookingStatusHistory::create([
            'booking_id' => $this->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'actor_type' => is_string($actor) ? $actor : ($actor ? get_class($actor) : null),
            'actor_id' => is_string($actor) ? null : $actor?->getKey(),
        ]);
        $this->syncPromotionRedemption($to);

        return true;
    }

    /**
     * A reserved Lucky redemption is used once the booking is paid, and its
     * campaign capacity is returned if the unpaid request closes instead.
     */
    private function syncPromotionRedemption(string $to): void
    {
        if ($to === self::STATUS_PAID) {
            app(PromotionService::class)->finalizeForBooking($this);
        } elseif (in_array($to, [self::STATUS_EXPIRED, self::STATUS_REJECTED, self::STATUS_CANCELLED], true)) {
            app(PromotionService::class)->releaseForBooking($this, 'Booking '.$to.' before a verified payment.');
        }
    }

    /**
     * Idempotently mark an approved booking as paid.
     *
     * @return bool true when this call performed the transition
     */
    public function markPaid(string $note = 'Payment completed.'): bool
    {
        if ($this->payment_plan_version === 2) {
            throw new PaymentGatewayException('Installment bookings require a verified financial receipt.');
        }

        return $this->transitionTo(
            self::STATUS_PAID,
            [self::STATUS_APPROVED],
            ['paid_at' => now(), 'payment_status' => self::PAYMENT_PAID],
            $note
        );
    }
}
