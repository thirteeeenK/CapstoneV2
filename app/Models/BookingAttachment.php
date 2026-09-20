<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAttachment extends Model
{
    public const KINDS = ['ticket', 'voucher', 'confirmation', 'receipt', 'other'];

    public const TERMINAL_STATUSES = [
        Booking::STATUS_REJECTED,
        Booking::STATUS_CANCELLED,
        Booking::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'booking_id',
        'booking_item_id',
        'kind',
        'label',
        'path',
        'mime',
        'size',
        'uploaded_by_admin_id',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function item()
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id');
    }

    public function uploader()
    {
        return $this->belongsTo(AdminModel::class, 'uploaded_by_admin_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
