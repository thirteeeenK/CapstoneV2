<?php

use App\Models\AdminModel;
use App\Models\Faq;

beforeEach(function () {
    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@faqtest.com',
        'password' => 'password',
    ]);
});

it('lists FAQs for the admin', function () {
    Faq::create([
        'question' => 'Does SunnyTrips support airline ticket booking?',
        'answer' => 'No, we do not.',
        'keywords' => 'airline, flight',
        'category' => 'Services',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.faqs.index'))
        ->assertOk()
        ->assertSee('FAQ Manager')
        ->assertSee('Does SunnyTrips support airline ticket booking?');
});

it('creates a new FAQ', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.faqs.store'), [
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept credit cards, GCash, and bank transfers.',
            'keywords' => 'payment, pay, gcash, credit card',
            'category' => 'Booking',
            'sort_order' => 2,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.faqs.index'));

    $this->assertDatabaseHas('faqs', [
        'question' => 'What payment methods do you accept?',
        'answer' => 'We accept credit cards, GCash, and bank transfers.',
        'is_active' => true,
    ]);
});

it('updates an existing FAQ', function () {
    $faq = Faq::create([
        'question' => 'Old question?',
        'answer' => 'Old answer.',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.faqs.update', $faq->id), [
            'question' => 'New question?',
            'answer' => 'New answer.',
            'keywords' => 'updated, fresh',
            'category' => 'Policies',
            'sort_order' => 5,
            'is_active' => 0,
        ])
        ->assertRedirect(route('admin.faqs.index'));

    $faq->refresh();
    expect($faq->question)->toBe('New question?')
        ->and($faq->answer)->toBe('New answer.')
        ->and($faq->category)->toBe('Policies')
        ->and($faq->sort_order)->toBe(5)
        ->and($faq->is_active)->toBeFalse();
});

it('toggles FAQ visibility', function () {
    $faq = Faq::create([
        'question' => 'Toggle me?',
        'answer' => 'Answer.',
        'sort_order' => 0,
        'is_active' => true,
        'show_on_landing' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.faqs.toggle-visibility', $faq->id));

    $faq->refresh();
    expect($faq->is_active)->toBeFalse()
        ->and($faq->show_on_landing)->toBeTrue();
});

it('toggles FAQ page visibility without changing SunnyBot visibility', function () {
    $faq = Faq::factory()->create([
        'is_active' => true,
        'show_on_landing' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.faqs.toggle-page-visibility', $faq->id));

    $faq->refresh();
    expect($faq->is_active)->toBeTrue()
        ->and($faq->show_on_landing)->toBeFalse();
});

it('deletes an FAQ', function () {
    $faq = Faq::create([
        'question' => 'Delete me?',
        'answer' => 'Answer.',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.faqs.destroy', $faq->id))
        ->assertRedirect(route('admin.faqs.index'));

    expect(Faq::find($faq->id))->toBeNull();
});

it('rejects an FAQ without a question', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.faqs.store'), [
            'question' => '',
            'answer' => 'Answer.',
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('question');
});

it('stores each independent FAQ visibility combination', function (string $label, bool $isActive, bool $showOnLanding) {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.faqs.store'), [
            'question' => "Audience {$label}?",
            'answer' => 'Audience answer.',
            'is_active' => (int) $isActive,
            'show_on_landing' => (int) $showOnLanding,
        ])
        ->assertRedirect(route('admin.faqs.index'));

    $this->assertDatabaseHas('faqs', [
        'question' => "Audience {$label}?",
        'is_active' => $isActive,
        'show_on_landing' => $showOnLanding,
    ]);
})->with([
    'both' => ['both', true, true],
    'SunnyBot only' => ['chatbot_only', true, false],
    'FAQ page only' => ['faq_only', false, true],
    'hidden from both' => ['hidden', false, false],
]);

it('shows clear channel indicators and all four filters', function () {
    Faq::factory()->create(['is_active' => true, 'show_on_landing' => true]);
    Faq::factory()->create(['is_active' => false, 'show_on_landing' => true]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.faqs.index'))
        ->assertOk()
        ->assertSee('SunnyBot Enabled')
        ->assertSee('FAQ Page Visible')
        ->assertSee('SunnyBot: On')
        ->assertSee('FAQ Page: On')
        ->assertSee('FAQ Page Only')
        ->assertSee('Hidden from Both');
});

it('filters the FAQ-page-only visibility combination precisely', function () {
    Faq::factory()->create([
        'question' => 'Visible in both channels?',
        'is_active' => true,
        'show_on_landing' => true,
    ]);
    Faq::factory()->create([
        'question' => 'Visible only on the FAQ page?',
        'is_active' => false,
        'show_on_landing' => true,
    ]);
    Faq::factory()->create([
        'question' => 'Hidden from both channels?',
        'is_active' => false,
        'show_on_landing' => false,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.faqs.index', ['visibility' => 'faq_only']))
        ->assertOk()
        ->assertSee('Visible only on the FAQ page?')
        ->assertDontSee('Visible in both channels?')
        ->assertDontSee('Hidden from both channels?');
});
