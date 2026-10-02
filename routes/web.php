<?php

use App\Http\Controllers\Perfect10\DashboardController;
use App\Http\Controllers\Perfect10\FixturesController;
use App\Http\Controllers\Perfect10\PlaceholderPageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->name('dashboard');

Route::get('/fixtures', [FixturesController::class, 'index'])
    ->name('fixtures');

Route::get('/leaderboards', function (
    Request $request,
    PlaceholderPageController $controller
) {
    return $controller->show($request, 'leaderboards');
})->name('leaderboards');

Route::get('/clubs', function (
    Request $request,
    PlaceholderPageController $controller
) {
    return $controller->show($request, 'clubs');
})->name('clubs');

Route::get('/gameweek', function (
    Request $request,
    PlaceholderPageController $controller
) {
    return $controller->show($request, 'gameweek');
})->name('gameweek');

Route::get('/stats', function (
    Request $request,
    PlaceholderPageController $controller
) {
    return $controller->show($request, 'stats');
})->name('stats');

require __DIR__.'/settings.php';
