<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_number',
    'chat_session_id',
    'user_id',
    'assigned_admin_id',
    'status',
    'requested_at',
    'assigned_at',
    'returned_to_ai_at',
    'resolved_at',
])]
class SupportInquiry extends Model
{
    use HasFactory;

    public const STATUS_AI_ACTIVE = 'AI_ACTIVE';
    public const STATUS_PENDING = 'PENDING_ASSIGNMENT';
    public const STATUS_HUMAN_ACTIVE = 'HUMAN_SUPPORT_ACTIVE';
    public const STATUS_RETURNED_AI = 'RETURNED_TO_AI';
    public const STATUS_RESOLVED = 'RESOLVED';

    protected $casts = [
        'requested_at' => 'datetime',
        'assigned_at' => 'datetime',
        'returned_to_ai_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'assigned_admin_id');
    }
}
