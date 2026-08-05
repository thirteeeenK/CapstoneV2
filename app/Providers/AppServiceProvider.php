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

        // ESTABLISH RATE LIMITIONS FOR THE APPLICATION - IP BASED FOR NOW BUT SHOULD BE IP AND ID IN THE FUTURE

        // -- ADMIN RATE LIMIT -- (*CHANGES POSSIBLE)
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // -- USER RATE LIMIT -- (*CHANGES POSSIBLE)
        RateLimiter::for('users', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // -- CHATBOT RATE LIMIT *not logged in user -- (*CHANGES POSSIBLE) 
        RateLimiter::for('chatbot', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // -- CHATBOT RATE LIMIT *LOGGED IN USER -- (*CHANGES POSSIBLE) 
        RateLimiter::for('chatbot', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
