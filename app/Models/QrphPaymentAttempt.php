<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'booking_id', 'booking_payment_request_id', 'intent_id', 'method_id', 'method_generation', 'qr_code_id', 'payment_id', 'amount', 'currency', 'livemode', 'status', 'qr_image', 'test_url', 'expires_at', 'confirmed_at', 'last_checked_at'])]
#[Hidden(['qr_image', 'test_url'])]
class QrphPaymentAttempt extends Model
{
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $attributes = ['method_generation' => 1];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method_generation' => 'integer',
            'livemode' => 'boolean',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
