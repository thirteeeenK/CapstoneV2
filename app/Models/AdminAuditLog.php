<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_id',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(AdminModel::class, 'admin_id');
    }

    /**
     * Price direction for catalog badges: latest price edit per item within
     * the window, where both old and new values contain the field (create
     * rows and non-price edits are ignored). Decorative — returns [] on error.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, array{dir: 'up'|'down', old: float, date: string}>
     */
    public static function recentPriceChanges(string $auditableType, array $ids, string $priceField, int $days = 30): array
    {
        $changes = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return $changes;
        }

        try {
            $logs = self::where('auditable_type', $auditableType)
                ->whereIn('auditable_id', $ids)
                ->where('created_at', '>=', now()->subDays($days))
                ->orderByDesc('created_at')
                ->get(['auditable_id', 'old_values', 'new_values', 'created_at']);

            foreach ($logs as $log) {
                $id = (int) $log->auditable_id;
                if (isset($changes[$id])) {
                    continue;
                }
                $oldVal = self::priceAsFloat($log->old_values[$priceField] ?? null);
                $newVal = self::priceAsFloat($log->new_values[$priceField] ?? null);
                if ($oldVal === null || $newVal === null || $oldVal == $newVal) {
                    continue;
                }
                $changes[$id] = [
                    'dir' => $newVal > $oldVal ? 'up' : 'down',
                    'old' => $oldVal,
                    'date' => $log->created_at->format('M d, Y'),
                ];
            }
        } catch (\Throwable $e) {
            Log::debug('recentPriceChanges failed: '.$e->getMessage());
        }

        return $changes;
    }

    /**
     * First number in a price value: numeric columns pass through, freeform
     * activity rates like "₱1,200/person" yield 1200.0.
     */
    private static function priceAsFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (! is_string($value) || ! preg_match('/[\d,]+(?:\.\d+)?/', $value, $m)) {
            return null;
        }

        return (float) str_replace(',', '', $m[0]);
    }
}
