<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $table = 'payment_webhook_events';

    protected $fillable = [
        'gateway',
        'event_id',
        'booking_code',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
