<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

// Public site. Route names used by the menu in components/layout.blade.php, added sprint by sprint:
// home, restaurants.index, cities.index, cuisines.index, submit.create, contact.create
Route::get('/', HomeController::class)->name('home');

// For search engines. robots.txt is generated here, so the static file public/robots.txt must not exist (it would win).
// withoutMiddleware('web'): no session or cookies on these, because they are marked as cacheable for everyone.
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->withoutMiddleware('web')->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->withoutMiddleware('web')->name('robots');

Route::get('/restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
Route::get('/restaurant/{restaurant:slug}', [RestaurantController::class, 'show'])->name('restaurants.show');
// throttle:reviews = rate limit defined in AppServiceProvider (stops floods of fake reviews)
Route::post('/restaurant/{restaurant:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:reviews')->name('reviews.store');

// Visitors suggest a restaurant (saved as a pending submission for the owner). Honeypot in the form + throttle:submissions
Route::get('/submit-restaurant', [SubmissionController::class, 'create'])->name('submit.create');
Route::post('/submit-restaurant', [SubmissionController::class, 'store'])->middleware('throttle:submissions')->name('submit.store');

// Contact form: messages for the owner, shown in the admin under Messages. Honeypot in the form + throttle:contact
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

// {city:slug}: look the city up by its slug (the readable part of the address) instead of its id
Route::get('/cities', [CityController::class, 'index'])->name('cities.index');
Route::get('/city/{city:slug}', [CityController::class, 'show'])->name('cities.show');
Route::get('/cuisines', [CuisineController::class, 'index'])->name('cuisines.index');
Route::get('/cuisine/{cuisine:slug}', [CuisineController::class, 'show'])->name('cuisines.show');

// Admin panel. Sign-in required: every page below needs a logged-in owner (create the account with: php artisan admin:create).
// Keep HTTP Basic Auth on the server as a second lock before going live (DEPLOYMENT.md).
// (Admin\... keeps the admin controllers apart from public ones that share a name, e.g. RestaurantController.)
Route::prefix('admin')->name('admin.')->group(function () {
    // Sign-in page: only for people who are not signed in. throttle: 5 tries per minute per address stops password guessing.
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\LoginController::class, 'create'])->name('login');
        Route::post('login', [Admin\LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

        // Forgot password: ask for an email, get a link, choose a new password. Each step is rate limited.
        Route::get('forgot-password', [Admin\PasswordResetController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [Admin\PasswordResetController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
        Route::get('reset-password/{token}', [Admin\PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('reset-password', [Admin\PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [Admin\LoginController::class, 'destroy'])->name('logout');

        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::resource('restaurants', Admin\RestaurantController::class)->except('show');

        Route::put('restaurants/{restaurant}/hours', [Admin\RestaurantHoursController::class, 'update'])->name('restaurants.hours.update');

        // Photos. scoped(): a photo is only found through the restaurant it belongs to (otherwise 404)
        Route::post('restaurants/{restaurant}/cover', [Admin\RestaurantCoverController::class, 'store'])->name('restaurants.cover.store');
        Route::delete('restaurants/{restaurant}/cover', [Admin\RestaurantCoverController::class, 'destroy'])->name('restaurants.cover.destroy');
        Route::resource('restaurants.images', Admin\RestaurantImageController::class)->only(['store', 'update', 'destroy'])->scoped();

        Route::resource('cities', Admin\CityController::class)->except('show');
        Route::resource('cuisines', Admin\CuisineController::class)->except('show');
        Route::resource('amenities', Admin\AmenityController::class)->except('show');

        // Moderation: approve / reject (update) and delete. Only approved reviews are ever shown to visitors.
        Route::resource('reviews', Admin\ReviewController::class)->only(['index', 'update', 'destroy']);

        // Restaurants suggested by visitors. "Create restaurant" opens restaurants/create?submission=ID, pre-filled.
        Route::resource('submissions', Admin\SubmissionController::class)->only(['index', 'update', 'destroy']);

        // Messages from the public contact form: read, mark read/unread (update) and delete
        Route::resource('messages', Admin\MessageController::class)->only(['index', 'update', 'destroy']);

        // The admin accounts: who can sign in to this panel
        Route::resource('users', Admin\UserController::class)->except('show');
    });
});

// LOCAL DEVELOPMENT ONLY (not registered in production): see an error page without breaking the site.
// Open /preview-error/404, /preview-error/500 and so on.
if (app()->isLocal()) {
    // Style guide with an example of every reusable component: open /admin/components
    Route::get('/admin/components', fn () => view('admin.components'))->middleware('auth')->name('admin.components');

    Route::get('/preview-error/{code}', fn (int $code) => abort($code, '', $code === 429 ? ['Retry-After' => 90] : []))
        ->whereIn('code', [403, 404, 405, 419, 429, 500, 503]);
}
