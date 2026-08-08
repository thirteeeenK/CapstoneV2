<?php

use Illuminate\Support\Facades\Blade;

it('renders the thinking orb component with defaults', function () {
    $html = html_entity_decode(Blade::render('<x-thinking-orb />'), ENT_QUOTES);

    expect($html)->toContain('<canvas')
        ->and($html)->toContain('role="img"')
        ->and($html)->toContain("state: 'working'")
        ->and($html)->toContain('size: 40')
        ->and($html)->toContain('paused: false')
        ->and($html)->toContain('light: false');
});

it('passes state, size and light flags through to the orb', function () {
    $html = html_entity_decode(Blade::render('<x-thinking-orb state="searching" :size="18" light />'), ENT_QUOTES);

    expect($html)->toContain("state: 'searching'")
        ->and($html)->toContain('size: 18')
        ->and($html)->toContain('light: true')
        ->and($html)->toContain('style="width: 18px; height: 18px; display: block;"')
        ->and($html)->toContain('aria-label="Searching"');
});

it('supports paused and custom label attributes', function () {
    $html = html_entity_decode(Blade::render('<x-thinking-orb state="composing" :size="28" :paused="true" label="Composing your reply" />'), ENT_QUOTES);

    expect($html)->toContain("state: 'composing'")
        ->and($html)->toContain('size: 28')
        ->and($html)->toContain('paused: true')
        ->and($html)->toContain('aria-label="Composing your reply"');
});

it('renders the orb inside the booking report analyze button', function () {
    $this->withoutVite();

    $admin = App\Models\AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'orb-admin@sunnytripstest.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('thinkingOrb({ state: ', false)
        ->assertSee('searching', false)
        ->assertSee('size: 16', false)
        ->assertSee('light: true', false);
});
