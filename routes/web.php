<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// TEMPORARY: delete this route and resources/views/theme-test.blade.php after checking the theme
Route::view('/theme-test', 'theme-test');
