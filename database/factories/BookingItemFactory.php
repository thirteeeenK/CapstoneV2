<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    protected $model = BookingItem::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'item_type' => 'room',
            'item_id' => 1,
            'item_title' => fake()->words(3, true),
            'item_subtitle' => '1 night stay',
            'hotel_name' => fake()->company(),
            'unit_price' => 2000.00,
            'quantity' => 1,
            'selected_pax' => 2,
            'check_in_date' => null,
            'check_out_date' => null,
            'nights' => 1,
            'subtotal' => 2000.00,
            'availability_status' => BookingItem::AVAIL_PENDING,
            'item_snapshot' => [],
        ];
    }
}
