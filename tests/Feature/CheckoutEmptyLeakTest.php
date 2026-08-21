<?php

use App\Models\Booking;
use App\Models\CartItem;

it('does not leak empty-basket error onto booking page after checkout redirect', function () {
    $user = onboardedUser();

    // Ensure cart is empty
    expect(CartItem::where('user_id', $user->id)->count())->toBe(0);

    // Visit checkout with empty cart -> redirects to cart.index without error flash
    $res = $this->actingAs($user)->get(route('checkout.index'));
    $res->assertRedirect(route('cart.index'));
    $res->assertSessionMissing('error');

    // Create a booking for the same user and visit its page - should not show leaked error
    $booking = Booking::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $show = $this->actingAs($user)->get(route('booking.show', $booking->booking_code));
    $show->assertOk();
    $show->assertDontSee('Your Trip Basket is empty');
    $show->assertDontSee('Please add items before checking out');
});

it('redirects empty checkout to trip basket without flashing an error', function () {
    $user = onboardedUser();

    $this->actingAs($user)->get(route('checkout.index'))
        ->assertRedirect(route('cart.index'))
        ->assertSessionMissing('error');
});
