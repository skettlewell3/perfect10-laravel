<?php

use App\Http\Controllers\Perfect10\DashboardController;
use App\Http\Controllers\Perfect10\FixturesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard');

Route::get('/fixtures', [FixturesController::class, 'index'])
    ->name('fixtures');

require __DIR__.'/settings.php';
