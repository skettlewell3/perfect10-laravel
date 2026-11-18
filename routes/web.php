<?php

use App\Http\Controllers\Auth\ElsewhereAuthController;
use App\Http\Controllers\Perfect10\ClubsController;
use App\Http\Controllers\Perfect10\DashboardController;
use App\Http\Controllers\Perfect10\FixtureLeaderboardController;
use App\Http\Controllers\Perfect10\FixtureLiveDataController;
use App\Http\Controllers\Perfect10\FixturePredictionLiveDataController;
use App\Http\Controllers\Perfect10\FixturesController;
use App\Http\Controllers\Perfect10\GameweekController;
use App\Http\Controllers\Perfect10\LeaderboardController;
use App\Http\Controllers\Perfect10\MatchController;
use App\Http\Controllers\Perfect10\PredictionSubmissionController;
use App\Http\Controllers\Perfect10\ProfileController;
use App\Http\Controllers\Perfect10\StatsController;
use App\Http\Middleware\EnsureElsewhereAuthenticated;
use App\Http\Middleware\EnsurePerfect10Profile;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Support\Facades\Route;

Route::get(
    '/auth/elsewhere/redirect',
    [ElsewhereAuthController::class, 'redirect']
)->name('elsewhere.auth.redirect');

Route::get(
    '/auth/elsewhere/callback',
    [ElsewhereAuthController::class, 'callback']
)->name('elsewhere.auth.callback');

Route::middleware(EnsureElsewhereAuthenticated::class)->group(function (): void {

    Route::get('/', function (
        Perfect10RequestContext $context
    ) {
        $flavour = $context->selectedFlavourData();

        return redirect()->route('dashboard', [
            'flavour' => strtolower($flavour['flavour_code']),
        ]);
    });

    Route::post(
        '/auth/elsewhere/logout',
        [ElsewhereAuthController::class, 'logout']
    )->name('elsewhere.auth.logout');

    Route::get('/profile', [ProfileController::class, 'index'])
        ->name('perfect10.profile');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('perfect10.profile.update');

    Route::post('/profile/club', [ProfileController::class, 'createClub'])
        ->name('perfect10.profile.club.store');

    Route::prefix('{flavour}')->group(function () {

        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/fixtures', [FixturesController::class, 'index'])
            ->name('fixtures');

        Route::get(
            '/fixtures/{fixture}/leaderboard',
            [FixtureLeaderboardController::class, 'show']
        )
            ->whereNumber('fixture')
            ->name('fixtures.leaderboard');

        Route::get(
            '/fixtures/{fixture}/data',
            [FixtureLiveDataController::class, 'show']
        )
            ->whereNumber('fixture')
            ->name('fixtures.live-data');

        Route::get(
            '/fixtures/{fixture}/prediction-data',
            [FixturePredictionLiveDataController::class, 'show']
        )
            ->whereNumber('fixture')
            ->name('fixtures.prediction-data');

        Route::get('/gameweek', [GameweekController::class, 'index'])
            ->name('gameweek');

        Route::get('/gameweek/{week}', [GameweekController::class, 'index'])
            ->whereNumber('week')
            ->name('gameweek.show');

        Route::get('/matches/{fixture}', [MatchController::class, 'show'])
            ->whereNumber('fixture')
            ->name('matches.show');

        Route::post(
            '/predictions',
            [PredictionSubmissionController::class, 'store']
        )
            ->middleware(EnsurePerfect10Profile::class)
            ->name('predictions.store');

        Route::get('/leaderboards', [LeaderboardController::class, 'index'])
            ->name('leaderboards');

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

        Route::get('/stats', [StatsController::class, 'index'])
            ->name('stats');
    });

});

require __DIR__.'/settings.php';
