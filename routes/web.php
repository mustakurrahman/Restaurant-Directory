<?php

use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

Route::get('/', function () {
    return view('welcome');
});

// Admin panel. No login by owner's decision: protect /admin with HTTP Basic Auth on the server before going live.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('cities', CityController::class)->except('show');

    // TEMPORARY: preview of the shared components. Delete with resources/views/admin/components-test.blade.php
    Route::get('/components-test', function () {
        // Fake a failed form so the preview shows the error style (real forms get this automatically)
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'name_error' => 'The name field is required.',
        ])));

        return view('admin.components-test');
    })->name('components');
});

// TEMPORARY: delete this route and resources/views/theme-test.blade.php after checking the theme
Route::view('/theme-test', 'theme-test');
