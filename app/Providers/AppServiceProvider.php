<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use our dark-themed page links everywhere ->links() is called
        Paginator::defaultView('pagination.dark');

        // Rate limit for public forms: at most 3 reviews per 10 minutes from one visitor (IP address).
        // Going over shows our friendly 429 page that says how long to wait.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perMinutes(10, 3)->by($request->ip()));
    }
}
