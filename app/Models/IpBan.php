<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    protected $table = 'ip_bans';

    protected $fillable = [
        'ip_address',
        'ban_level',
        'reason',
        'banned_at',
        'expires_at',
        'banned_by',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(AdminModel::class, 'banned_by');
    }

    public function isWarned(): bool
    {
        return $this->ban_level === 'warning';
    }

    public function isPermanent(): bool
    {
        return $this->ban_level === 'permanent';
    }

    public function isTemporary(): bool
    {
        return $this->ban_level === 'temporary'
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function isBlocked(): bool
    {
        return $this->isPermanent() || $this->isTemporary();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('ban_level', 'permanent')
                ->orWhere(function ($q) {
                    $q->where('ban_level', 'temporary')
                        ->where('expires_at', '>', now());
                });
        });
    }

    public static function isBanned(string $ip): bool
    {
        return static::active()->where('ip_address', $ip)->exists();
    }
}
