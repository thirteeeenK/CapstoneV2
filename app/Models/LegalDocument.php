<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $table = 'legal_documents';

    protected $fillable = [
        'key',
        'title',
        'content',
        'version',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public static function for($key): ?self
    {
        return static::where('key', $key)->first();
    }

    public function asSections(): array
    {
        return $this->content ?? [];
    }
}