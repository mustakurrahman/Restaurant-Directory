<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // While developing (and in tests) a hidden "query per row" problem (N+1) throws an error instead of quietly
        // slowing the site down. Switched off in production, where an error page would be worse than a slow query.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Use our dark-themed page links everywhere ->links() is called
        Paginator::defaultView('pagination.dark');

        // Rate limit for public forms: at most 3 reviews per 10 minutes from one visitor (IP address).
        // Going over shows our friendly 429 page that says how long to wait.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perMinutes(10, 3)->by($request->ip()));

        // Suggestions are rarer than reviews, so the limit is stricter: 3 per hour per visitor
        RateLimiter::for('submissions', fn (Request $request) => Limit::perHour(3)->by($request->ip()));

        // Contact messages: a real person rarely needs more than a few per hour
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
    }
}
