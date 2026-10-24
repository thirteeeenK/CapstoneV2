<?php

use App\Models\AdminModel;

beforeEach(function () {
    $this->admin = AdminModel::create([
        'name' => 'Test Admin',
        'email' => 'admin@livelisttest.com',
        'password' => 'password',
    ]);
});

it('renders list pages with the shared live search and its retry alert', function (string $route) {
    $this->actingAs($this->admin, 'admin')->get(route($route))
        ->assertOk()
        ->assertSee('adminLiveList({', false)
        ->assertSee('@click="retryResults()"', false);
})->with(['admin.activities.index', 'admin.addons.index', 'admin.inventory.index', 'view-listings']);
