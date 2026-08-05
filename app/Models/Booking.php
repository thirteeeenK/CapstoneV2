<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'bookings';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const HOLD_STATUSES = ['pending', 'approved', 'paid'];

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PARTIAL = 'partial';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
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
        'expired_at',
        'admin_notes',
        'reviewed_by_admin_id',
        'total_amount',
        'discount_amount',
        'tax_amount',
        'net_amount',
        'payment_status',
        'payment_method',
        'payment_reference',
        'gateway',
        'gateway_reference',
        'payment_url',
        'contact_name',
        'contact_email',
        'contact_phone',
        'special_requests',
        'guest_manifest',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'guest_manifest' => 'array',
        'approved_at' => 'datetime',
        'payment_deadline' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(AdminModel::class, 'reviewed_by_admin_id');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function history()
    {
        return $this->hasMany(BookingStatusHistory::class)->orderBy('id', 'desc');
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
    }
}
