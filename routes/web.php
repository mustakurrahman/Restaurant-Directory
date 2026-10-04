<?php

use App\Http\Controllers\Admin\AmenityController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\CuisineController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin panel. No login by owner's decision: protect /admin with HTTP Basic Auth on the server before going live.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Only the list for now; add, edit and delete are added in the next Sprint 3 steps
    Route::resource('restaurants', RestaurantController::class)->only('index');
    Route::resource('cities', CityController::class)->except('show');
    Route::resource('cuisines', CuisineController::class)->except('show');
    Route::resource('amenities', AmenityController::class)->except('show');
});
