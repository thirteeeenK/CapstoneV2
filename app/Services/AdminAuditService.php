<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AdminModel;
use Illuminate\Database\Eloquent\Model;

class AdminAuditService
{
    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     */
    public static function recordAction(Model $model, string $action, array $before, array $after, AdminModel $admin): void
    {
        AdminAuditLog::create([
            'admin_id' => $admin->id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $before,
            'new_values' => ['action' => $action, ...$after],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Persist an audit row for an admin action on a model.
     *
     * Action type is not stored — Chapter 3 requirement is admin ID +
     * timestamp + what changed. The nature of the change is inferred from
     * $oldValues (null = create, populated = update/delete).
     */
    public static function log(Model $model, ?array $oldValues = null, ?AdminModel $admin = null): void
    {
        $admin ??= auth('admin')->user();

        AdminAuditLog::create([
            'admin_id' => $admin?->getKey(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $oldValues === null ? $model->getAttributes() : $model->getChanges(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
