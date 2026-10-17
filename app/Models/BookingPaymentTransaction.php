<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'booking_payment_request_id', 'original_transaction_id', 'gateway', 'livemode', 'source_reference', 'kind', 'amount', 'currency', 'allocated', 'occurred_at', 'verified_by_admin_id', 'note'])]
class BookingPaymentTransaction extends Model
{
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'livemode' => 'boolean', 'allocated' => 'boolean', 'occurred_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
