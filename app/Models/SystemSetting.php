<?php

namespace App\Models;

use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['section', 'values', 'revision', 'updated_by'])]
class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['values' => 'array', 'revision' => 'integer'];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(AdminModel::class, 'updated_by');
    }
}
