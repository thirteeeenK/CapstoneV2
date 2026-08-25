<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'type',
    'name',
    'icon',
    'description',
    'image_path',
    'image_url',
    'sort_order',
    'is_active',
])]
class OnboardingOption extends Model
{
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getResolvedImageUrlAttribute(): ?string
    {
        if (! empty($this->image_url) && filter_var($this->image_url, FILTER_VALIDATE_URL)) {
            return $this->image_url;
        }

        if (! empty($this->image_path) && Storage::disk('public')->exists($this->image_path)) {
            return asset('storage/'.$this->image_path);
        }

        return null;
    }
}
