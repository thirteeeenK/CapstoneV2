<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'booking_id', 'purpose', 'amount', 'currency', 'gateway', 'livemode', 'quote_amount', 'status', 'provider_reference', 'checkout_url', 'due_at', 'confirmed_at'])]
class BookingPaymentRequest extends Model
{
    use HasFactory;

    public const ACTIVE = ['creating', 'pending', 'awaiting_verification', 'processing', 'review_required'];

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return ['amount' => 'integer', 'quote_amount' => 'integer', 'livemode' => 'boolean', 'due_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
