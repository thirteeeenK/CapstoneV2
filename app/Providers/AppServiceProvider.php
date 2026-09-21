<?php

namespace App\Providers;

use App\Listeners\SeedDemoBookingsListener;
use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\RoomType;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        Event::listen(Registered::class, SeedDemoBookingsListener::class);

        Relation::morphMap([
            'hotel' => HotelModel::class,
            'room' => RoomType::class,
            'activity' => ActivityModel::class,
            'addon' => AddOnModel::class,
            'package' => Package::class,
        ]);

        Blade::anonymousComponentPath(resource_path('views/adminComponents'), 'admin-components');
        Blade::anonymousComponentPath(resource_path('views/adminComponents'), 'adminComponents');

        // ESTABLISH RATE LIMITS FOR THE APPLICATION.
        // Keyed by authenticated user id when available, falling back to IP.

        // -- AUTHENTICATED USER ACTIONS (login, register, password resets,
        //    booking/checkout mutations, profile updates, notifications) --
        RateLimiter::for('users', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(30)->by('users:'.$key);
        });

        // -- ADMIN AREA ACTIONS (CRUD, bookings, user management, uploads) --
        RateLimiter::for('admin', function (Request $request) {
            $key = $request->user('admin')?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(120)->by('admin:'.$key);
        });

        // -- CART OPERATIONS (guest + logged-in, session or user keyed) --
        RateLimiter::for('cart', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier()
                ?? $request->session()->getId()
                ?? $request->ip();

            return Limit::perMinute(30)->by('cart:'.$key);
        });

        // -- AI RECOMMENDATION / CHATBOT (expensive model calls) --
        RateLimiter::for('ai', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(10)->by('ai:'.$key);
        });

        // -- CHAT WIDGET POLLING / HISTORY (DB reads only; keeps the 10/min 'ai'
        //    budget reserved for the LLM calls. 5s active polling = 12/min. --
        RateLimiter::for('chat-poll', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(60)->by('chat-poll:'.$key);
        });

        // -- DSS PAGES / EXPLORER MAP APIs (DB reads + cached weather; guards
        //    against map-drag hammering without touching the LLM budget) --
        RateLimiter::for('dss', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(60)->by('dss:'.$key);
        });

        // -- CHATBOT GUEST LIMITER (IP-keyed burst; daily cap enforced in middleware) --
        RateLimiter::for('chat-guest', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(5)->by('chat-guest:'.$key);
        });

        // -- PAYMENT WEBHOOK (IP based; forged requests fail signature check) --
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(10)->by('webhook:'.$request->ip());
        });

        // Railway proxy terminates TLS; trustProxies fixes request URLs,
        // this covers console/queued URLs too. Local dev unaffected.
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
