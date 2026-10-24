<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'session_token',
    'user_id',
    'metadata',
])]
class ChatSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public static function generateToken(): string
    {
        return 'sess_'.Str::random(40);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * The newest message, so the support queue can eager-load it per ticket.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /**
     * The newest message the traveler sent (pending tickets preview this one).
     */
    public function latestUserMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('sender', 'user'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claimFor(User $user): void
    {
        $this->update(['user_id' => $user->id]);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}
