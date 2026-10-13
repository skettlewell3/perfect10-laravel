<?php

use App\Http\Controllers\Perfect10\ClubsController;
use App\Http\Controllers\Perfect10\DashboardController;
use App\Http\Controllers\Perfect10\FixturesController;
use App\Http\Controllers\Perfect10\LeaderboardController;
use App\Http\Controllers\Perfect10\PlaceholderPageController;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (
    FlavourContextService $flavourContext
) {
    $flavours = $flavourContext->availableFlavours();

    abort_if(
        $flavours->isEmpty(),
        404,
        'No active Perfect10 flavour is available.'
    );

    $flavour = $flavourContext->selectFlavour($flavours);

    return redirect()->route('dashboard', [
        'flavour' => strtolower($flavour['flavour_code']),
    ]);
});

Route::prefix('{flavour}')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/fixtures', [FixturesController::class, 'index'])
        ->name('fixtures');

    Route::get(
        '/leaderboards',
        [LeaderboardController::class, 'index']
    )->name('leaderboards');

    Route::get('/clubs', [ClubsController::class, 'index'])
        ->name('clubs');

    Route::get('/clubs/{club}', [ClubsController::class, 'show'])
        ->name('clubs.show');

    Route::post(
        '/clubs/{club}/invites',
        [ClubsController::class, 'sendInvite']
    )->name('clubs.invites.send');

    Route::post(
        '/clubs/invites/accept',
        [ClubsController::class, 'acceptInvite']
    )->name('clubs.invites.accept');

    Route::post(
        '/clubs/invites/decline',
        [ClubsController::class, 'declineInvite']
    )->name('clubs.invites.decline');
    
    Route::patch(
        '/clubs/{club}/members/role',
        [ClubsController::class, 'updateMemberRole']
    )->name('clubs.members.role');
    
    Route::delete(
        '/clubs/{club}/members',
        [ClubsController::class, 'removeMember']
    )->name('clubs.members.remove');

    Route::post(
        '/clubs/{club}/ownership-transfer',
        [ClubsController::class, 'requestOwnershipTransfer']
    )->name('clubs.ownership-transfer.request');
        
    Route::post(
        '/clubs/ownership-transfer/accept',
        [ClubsController::class, 'acceptOwnershipTransfer']
    )->name('clubs.ownership-transfer.accept');
    
    Route::post(
        '/clubs/ownership-transfer/decline',
        [ClubsController::class, 'declineOwnershipTransfer']
    )->name('clubs.ownership-transfer.decline');

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
});

require __DIR__.'/settings.php';