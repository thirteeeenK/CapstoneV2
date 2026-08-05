<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerCategoryRule extends Model
{
    use HasFactory;

    protected $table = 'passenger_category_rules';

    protected $fillable = [
        'category_name',
        'display_label',
        'adjustment_type',
        'amount',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Helper to get active rules formatted for frontend/backend calculations.
     */
    public static function getActiveRulesMap()
    {
        return static::where('is_active', true)->get()->keyBy('category_name');
    }
}
