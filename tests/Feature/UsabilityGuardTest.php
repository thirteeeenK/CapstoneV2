<?php

use App\Models\Package;

it('shows search guidance with a clear filters action when no packages match', function () {
    Package::factory()->create(['name' => 'Boracay Escape']);

    $this->get(route('packages.index', ['search' => 'zzzznoexistentquery']))
        ->assertSee('No packages match your search')
        ->assertSee('Clear Filters &amp; View All Packages', false);
});

it('renders the packages page title and search controls for first-time visitors', function () {
    Package::factory()->create(['name' => 'Boracay Escape']);

    $this->get(route('packages.index'))
        ->assertSee('All-Inclusive Vacation Deals')
        ->assertSee('Search by package name, type, or keyword...');
});

it('rejects checkout with missing contact details and shows the field error', function () {
    $user = onboardedUser();

    $response = $this->actingAs($user)->post(route('checkout.process'), [
        'contact_name' => '',
        'contact_email' => '',
        'contact_phone' => '',
    ]);

    $response->assertSessionHasErrors(['contact_name', 'contact_email', 'contact_phone']);
    $this->assertDatabaseCount('bookings', 0);
});

it('redirects guests who submit checkout to the login page', function () {
    $this->post(route('checkout.process'), [
        'contact_name' => 'Guest Person',
        'contact_email' => 'guest@example.com',
        'contact_phone' => '09171234567',
    ])->assertRedirect(route('login'));
});

it('renders the trip basket page for guests with an empty state', function () {
    $this->get(route('cart.index'))
        ->assertOk()
        ->assertSee('Trip Basket');
});

it('renders descriptive labels on the login form', function () {
    $this->get(route('login'))
        ->assertSee('Email')
        ->assertSee('Password');
});
