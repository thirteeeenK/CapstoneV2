<?php

use App\Models\Faq;

it('shows active FAQs grouped by category on the landing page', function () {
    Faq::create([
        'question' => 'How do I book a trip on SunnyTrips? (test)',
        'answer' => 'Add items to your Trip Basket and checkout.',
        'keywords' => 'book, trip',
        'category' => 'Booking',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    Faq::create([
        'question' => 'What payment methods do you accept? (test)',
        'answer' => 'Cards, e-wallets, and bank transfer.',
        'keywords' => 'payment, card',
        'category' => 'Payments',
        'sort_order' => 11,
        'is_active' => true,
    ]);

    $this->get(route('landing'))
        ->assertOk()
        ->assertSee('Frequently Asked Questions', false)
        ->assertSee('How do I book a trip on SunnyTrips? (test)', false)
        ->assertSee('What payment methods do you accept? (test)', false)
        ->assertSee('Booking', false)
        ->assertSee('Payments', false);
});

it('hides inactive FAQs from the landing page', function () {
    Faq::create([
        'question' => 'Hidden FAQ should not appear',
        'answer' => 'This is hidden.',
        'keywords' => 'hidden',
        'category' => 'General',
        'sort_order' => 20,
        'is_active' => false,
    ]);

    $this->get(route('landing'))
        ->assertOk()
        ->assertDontSee('Hidden FAQ should not appear', false);
});

it('does not render the FAQ section when there are no active FAQs', function () {
    // No FAQs created
    $this->get(route('landing'))
        ->assertOk()
        ->assertDontSee('Frequently Asked Questions', false);
});
