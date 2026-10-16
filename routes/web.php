<?php

use App\Http\Controllers\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
});

require __DIR__.'/settings.php';
