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

it('shows FAQ-page-only entries even when SunnyBot visibility is disabled', function () {
    Faq::create([
        'question' => 'Hidden FAQ should not appear',
        'answer' => 'This is hidden.',
        'keywords' => 'hidden',
        'category' => 'General',
        'sort_order' => 20,
        'is_active' => false,
        'show_on_landing' => true,
    ]);

    $this->get(route('landing'))
        ->assertOk()
        ->assertSee('Hidden FAQ should not appear', false);
});

it('hides chatbot-only FAQs from the landing page', function () {
    Faq::create([
        'question' => 'Chatbot-only FAQ should not appear',
        'answer' => 'Only SunnyBot knows this.',
        'keywords' => 'chatbot-only',
        'category' => 'General',
        'sort_order' => 21,
        'is_active' => true,
        'show_on_landing' => false,
    ]);

    $this->get(route('landing'))
        ->assertOk()
        ->assertDontSee('Chatbot-only FAQ should not appear', false);
});

it('does not render the FAQ section when there are no page-visible FAQs', function () {
    Faq::factory()->create([
        'is_active' => true,
        'show_on_landing' => false,
    ]);

    $this->get(route('landing'))
        ->assertOk()
        ->assertDontSee('Frequently Asked Questions', false);
});

it('renders markdown bold and lists in FAQ answers while escaping HTML', function () {
    Faq::create([
        'question' => 'Markdown FAQ test?',
        'answer' => "Yes — **Passenger Pricing Rules** apply:\n\n- **Student** — ₱50 off\n- Regular — none\n\n<script>alert(1)</script>",
        'keywords' => 'markdown',
        'category' => 'Pricing & Discounts',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    $content = $this->get(route('landing'))->assertOk()->getContent();

    expect($content)->toContain('<strong>Passenger Pricing Rules</strong>')
        ->toContain('<strong>Student</strong>')
        ->toContain('<ul')
        ->not->toContain('**Student**')
        ->toContain('&lt;script&gt;');
});
