<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SA LARAVEL 12 TOOO!! WALA NA TO SA LARAVEL 13
        // Blueprint::macro('vector',function(string $column, int $dimensions){
        //     return $this->addColumn('vector', $column, ['dimensions' => $dimensions]);
        // });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'room' => \App\Models\RoomType::class,
            'activity' => \App\Models\ActivityModel::class,
            'addon' => \App\Models\AddOnModel::class,
            'package' => \App\Models\Package::class,
        ]);

        Blade::anonymousComponentPath(resource_path('views/adminComponents'), 'admin-components');
        Blade::anonymousComponentPath(resource_path('views/adminComponents'), 'adminComponents');

        // ESTABLISH RATE LIMITS FOR THE APPLICATION.
        // Keyed by authenticated user id when available, falling back to IP.

        // -- AUTHENTICATED USER ACTIONS (login, register, password resets,
        //    booking/checkout mutations, profile updates, notifications) --
        RateLimiter::for('users', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(30)->by('users:' . $key);
        });

        // -- ADMIN AREA ACTIONS (CRUD, bookings, user management, uploads) --
        RateLimiter::for('admin', function (Request $request) {
            $key = $request->user('admin')?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(120)->by('admin:' . $key);
        });

        // -- CART OPERATIONS (guest + logged-in, session or user keyed) --
        RateLimiter::for('cart', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ?? $request->session()->getId()
                ?? $request->ip();

            return Limit::perMinute(30)->by('cart:' . $key);
        });

        // -- AI RECOMMENDATION / CHATBOT (expensive model calls) --
        RateLimiter::for('ai', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(10)->by('ai:' . $key);
        });

        // -- PAYMENT WEBHOOK (IP based; forged requests fail signature check) --
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(10)->by('webhook:' . $request->ip());
        });
    }
}
