<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

// Public site. Route names used by the menu in components/layout.blade.php, added sprint by sprint:
// home, restaurants.index, cities.index, cuisines.index, submit.create, contact.create
Route::get('/', HomeController::class)->name('home');
Route::get('/restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
Route::get('/restaurant/{restaurant:slug}', [RestaurantController::class, 'show'])->name('restaurants.show');
// throttle:reviews = rate limit defined in AppServiceProvider (stops floods of fake reviews)
Route::post('/restaurant/{restaurant:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:reviews')->name('reviews.store');

// Visitors suggest a restaurant (saved as a pending submission for the owner). Honeypot in the form + throttle:submissions
Route::get('/submit-restaurant', [SubmissionController::class, 'create'])->name('submit.create');
Route::post('/submit-restaurant', [SubmissionController::class, 'store'])->middleware('throttle:submissions')->name('submit.store');

// {city:slug}: look the city up by its slug (the readable part of the address) instead of its id
Route::get('/cities', [CityController::class, 'index'])->name('cities.index');
Route::get('/city/{city:slug}', [CityController::class, 'show'])->name('cities.show');
Route::get('/cuisines', [CuisineController::class, 'index'])->name('cuisines.index');
Route::get('/cuisine/{cuisine:slug}', [CuisineController::class, 'show'])->name('cuisines.show');

// Admin panel. No login by owner's decision: protect /admin with HTTP Basic Auth on the server before going live.
// (Admin\... keeps the admin controllers apart from public ones that share a name, e.g. RestaurantController.)
Route::prefix('admin')->name('admin.')->group(function () {
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
});

// LOCAL DEVELOPMENT ONLY (not registered in production): see an error page without breaking the site.
// Open /preview-error/404, /preview-error/500 and so on.
if (app()->isLocal()) {
    // Style guide with an example of every reusable component: open /admin/components
    Route::get('/admin/components', fn () => view('admin.components'))->name('admin.components');

    Route::get('/preview-error/{code}', fn (int $code) => abort($code, '', $code === 429 ? ['Retry-After' => 90] : []))
        ->whereIn('code', [403, 404, 405, 419, 429, 500, 503]);
}
