<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BookingPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'provider', 'sender_reference', 'normalized_reference', 'claimed_amount', 'verified_amount', 'status', 'submitted_at', 'verified_at', 'verified_by_admin_id', 'admin_note'])]
class BookingPayment extends Model
{
    /** @use HasFactory<BookingPaymentFactory> */
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'claimed_amount' => 'decimal:2',
            'verified_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'verified_by_admin_id');
    }
}
