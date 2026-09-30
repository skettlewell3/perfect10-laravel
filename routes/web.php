<?php

use App\Http\Controllers\Perfect10\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard');

require __DIR__.'/settings.php';
