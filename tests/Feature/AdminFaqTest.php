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
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.faqs.toggle-visibility', $faq->id));

    expect($faq->refresh()->is_active)->toBeFalse();
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
