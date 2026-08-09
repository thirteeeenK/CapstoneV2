<?php

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_token' => null,
            'item_type' => 'room',
            'item_id' => 1,
            'quantity' => 1,
            'check_in_date' => null,
            'check_out_date' => null,
            'selected_pax' => 2,
            'is_selected' => true,
            'notes' => null,
        ];
    }
}