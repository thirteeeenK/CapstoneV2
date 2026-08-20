<?php

use App\Models\AdminModel;
use App\Models\OnboardingOption;
use App\Models\User;

beforeEach(function () {
    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@onboardingtest.com',
        'password' => 'password',
    ]);
});

it('lists onboarding options for the admin', function () {
    OnboardingOption::create([
        'type' => 'vibe',
        'name' => 'Beachfront',
        'icon' => 'beach_access',
        'description' => 'Oceanfront views & white sand',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.onboarding-options.index'))
        ->assertOk()
        ->assertSee('Onboarding Options Manager')
        ->assertSee('Beachfront');
});

it('returns the table partial for ajax requests', function () {
    OnboardingOption::create([
        'type' => 'amenity',
        'name' => 'Infinity Pool',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->getJson(route('admin.onboarding-options.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Infinity Pool');
});

it('creates a new onboarding option', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.onboarding-options.store'), [
            'type' => 'vibe',
            'name' => 'Adventure & Thrills',
            'icon' => 'hiking',
            'description' => 'Water sports, treks & diving',
            'sort_order' => 1,
            'is_active' => 1,
        ])
        ->assertRedirect(route('admin.onboarding-options.index'));

    $this->assertDatabaseHas('onboarding_options', [
        'type' => 'vibe',
        'name' => 'Adventure & Thrills',
        'icon' => 'hiking',
        'is_active' => true,
    ]);
});

it('updates an existing onboarding option', function () {
    $option = OnboardingOption::create([
        'type' => 'traveler_type',
        'name' => 'Solo Traveler',
        'icon' => 'person',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.onboarding-options.update', $option->id), [
            'type' => 'traveler_type',
            'name' => 'Solo Explorer',
            'icon' => 'hiking',
            'description' => 'Independent & flexible',
            'sort_order' => 5,
            'is_active' => 0,
        ])
        ->assertRedirect(route('admin.onboarding-options.index'));

    $option->refresh();
    expect($option->name)->toBe('Solo Explorer')
        ->and($option->icon)->toBe('hiking')
        ->and($option->description)->toBe('Independent & flexible')
        ->and($option->sort_order)->toBe(5)
        ->and($option->is_active)->toBeFalse();
});

it('toggles onboarding option visibility', function () {
    $option = OnboardingOption::create([
        'type' => 'amenity',
        'name' => 'Spa Services',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.onboarding-options.toggle-active', $option->id));

    expect($option->refresh()->is_active)->toBeFalse();
});

it('deletes an onboarding option', function () {
    $option = OnboardingOption::create([
        'type' => 'vibe',
        'name' => 'Quiet & Serene',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->delete(route('admin.onboarding-options.destroy', $option->id))
        ->assertRedirect(route('admin.onboarding-options.index'));

    expect(OnboardingOption::find($option->id))->toBeNull();
});

it('rejects an onboarding option without a name or type', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.onboarding-options.store'), [
            'type' => '',
            'name' => '',
            'is_active' => 1,
        ])
        ->assertSessionHasErrors(['type', 'name']);
});

it('renders active options on the onboarding form and hides inactive ones', function () {
    $active = OnboardingOption::create([
        'type' => 'vibe',
        'name' => 'Dynamic Vibe',
        'icon' => 'beach_access',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    OnboardingOption::create([
        'type' => 'vibe',
        'name' => 'Hidden Vibe',
        'icon' => 'spa',
        'sort_order' => 1,
        'is_active' => false,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding.index'))
        ->assertOk()
        ->assertSee($active->name)
        ->assertDontSee('Hidden Vibe');
});
