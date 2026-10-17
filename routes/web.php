<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
});

require __DIR__.'/settings.php';
