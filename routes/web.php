<?php

use App\Http\Controllers\Admin\AmenityController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CuisineController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\RestaurantCoverController;
use App\Http\Controllers\Admin\RestaurantHoursController;
use App\Http\Controllers\Admin\RestaurantImageController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Public site. Route names used by the menu in components/layout.blade.php, added sprint by sprint:
// home, restaurants.index, cities.index, cuisines.index, submit.create, contact.create
Route::get('/', HomeController::class)->name('home');

// Admin panel. No login by owner's decision: protect /admin with HTTP Basic Auth on the server before going live.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('restaurants', RestaurantController::class)->except('show');

    Route::put('restaurants/{restaurant}/hours', [RestaurantHoursController::class, 'update'])->name('restaurants.hours.update');

    // Photos. scoped(): a photo is only found through the restaurant it belongs to (otherwise 404)
    Route::post('restaurants/{restaurant}/cover', [RestaurantCoverController::class, 'store'])->name('restaurants.cover.store');
    Route::delete('restaurants/{restaurant}/cover', [RestaurantCoverController::class, 'destroy'])->name('restaurants.cover.destroy');
    Route::resource('restaurants.images', RestaurantImageController::class)->only(['store', 'update', 'destroy'])->scoped();
    Route::resource('cities', CityController::class)->except('show');
    Route::resource('cuisines', CuisineController::class)->except('show');
    Route::resource('amenities', AmenityController::class)->except('show');
});
