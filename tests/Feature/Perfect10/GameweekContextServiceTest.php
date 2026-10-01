
<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Gameweek;
use App\Models\Perfect10\Stage;
use App\Services\Perfect10\GameweekContextService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

beforeEach(function () {
    // Never modify the live Perfect10 database during testing.
    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'GameweekContextService tests require in-memory SQLite.'
        );
    }

    Schema::create('gameweeks', function (Blueprint $table) {
        $table->id('gameweek_id');
        $table->unsignedBigInteger('campaign_id');
        $table->unsignedBigInteger('stage_id');
        $table->integer('gameweek_number');
        $table->string('status');
        $table->timestamp('prediction_open_at')->nullable();
        $table->timestamp('prediction_close_at')->nullable();
        $table->timestamps();
    });
});

/**
 * Create an in-memory Eloquent collection for selection tests.
 *
 * @param  array<int, array<string, mixed>>  $rows
 * @return Collection<int, Gameweek>
 */
function makeTestGameweeks(array $rows): Collection
{
    return new Collection(
        array_map(
            fn (array $attributes): Gameweek => new Gameweek($attributes),
            $rows
        )
    );
}

test('retrieves campaign gameweeks in numerical order', function () {
    DB::table('gameweeks')->insert([
        [
            'gameweek_id' => 1,
            'campaign_id' => 100,
            'stage_id' => 12,
            'gameweek_number' => 2,
            'status' => 'upcoming',
        ],
        [
            'gameweek_id' => 2,
            'campaign_id' => 100,
            'stage_id' => 11,
            'gameweek_number' => 1,
            'status' => 'finished',
        ],
        [
            'gameweek_id' => 3,
            'campaign_id' => 200,
            'stage_id' => 21,
            'gameweek_number' => 1,
            'status' => 'active',
        ],
    ]);

    $campaign = new Campaign([
        'campaign_id' => 100,
    ]);

    $service = app(GameweekContextService::class);

    $gameweeks = $service->gameweeksForCampaign($campaign);

    expect($gameweeks->pluck('gameweek_id')->all())
        ->toBe([2, 1]);
});

test('identifies the active gameweek', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 1,
            'status' => 'finished',
        ],
        [
            'gameweek_id' => 2,
            'status' => 'active',
        ],
        [
            'gameweek_id' => 3,
            'status' => 'upcoming',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect($service->activeGameweek($gameweeks)?->gameweek_id)
        ->toBe(2);
});

test('returns null when no gameweek is active', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 1,
            'status' => 'finished',
        ],
        [
            'gameweek_id' => 2,
            'status' => 'upcoming',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect($service->activeGameweek($gameweeks))
        ->toBeNull();
});

test('finds the next gameweek even when the collection is unordered', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 3,
            'gameweek_number' => 3,
            'status' => 'upcoming',
        ],
        [
            'gameweek_id' => 1,
            'gameweek_number' => 1,
            'status' => 'finished',
        ],
        [
            'gameweek_id' => 2,
            'gameweek_number' => 2,
            'status' => 'active',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect($service->nextGameweek($gameweeks)?->gameweek_id)
        ->toBe(3);
});

test('returns null when the active gameweek is the final gameweek', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 1,
            'gameweek_number' => 1,
            'status' => 'finished',
        ],
        [
            'gameweek_id' => 2,
            'gameweek_number' => 2,
            'status' => 'active',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect($service->nextGameweek($gameweeks))
        ->toBeNull();
});

test('returns null for next gameweek when none is active', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 1,
            'gameweek_number' => 1,
            'status' => 'finished',
        ],
        [
            'gameweek_id' => 2,
            'gameweek_number' => 2,
            'status' => 'upcoming',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect($service->nextGameweek($gameweeks))
        ->toBeNull();
});

test('validates a matching active stage and gameweek', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 10,
            'stage_id' => 50,
            'gameweek_number' => 5,
            'status' => 'active',
        ],
    ]);

    $activeStage = new Stage([
        'stage_id' => 50,
        'is_active' => true,
    ]);

    $service = app(GameweekContextService::class);

    $result = $service->validatedActiveGameweek(
        $gameweeks,
        $activeStage
    );

    expect($result?->gameweek_id)
        ->toBe(10);
});

test('allows both active stage and active gameweek to be absent', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 1,
            'status' => 'finished',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect(
        $service->validatedActiveGameweek($gameweeks, null)
    )->toBeNull();
});

test('rejects an active gameweek with no active stage', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 10,
            'stage_id' => 50,
            'status' => 'active',
        ],
    ]);

    $service = app(GameweekContextService::class);

    expect(
        fn () => $service->validatedActiveGameweek(
            $gameweeks,
            null
        )
    )->toThrow(RuntimeException::class);
});

test('rejects an active stage with no active gameweek', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 10,
            'stage_id' => 50,
            'status' => 'finished',
        ],
    ]);

    $activeStage = new Stage([
        'stage_id' => 50,
        'is_active' => true,
    ]);

    $service = app(GameweekContextService::class);

    expect(
        fn () => $service->validatedActiveGameweek(
            $gameweeks,
            $activeStage
        )
    )->toThrow(RuntimeException::class);
});

test('rejects a gameweek belonging to a different active stage', function () {
    $gameweeks = makeTestGameweeks([
        [
            'gameweek_id' => 10,
            'stage_id' => 50,
            'status' => 'active',
        ],
    ]);

    $activeStage = new Stage([
        'stage_id' => 60,
        'is_active' => true,
    ]);

    $service = app(GameweekContextService::class);

    expect(
        fn () => $service->validatedActiveGameweek(
            $gameweeks,
            $activeStage
        )
    )->toThrow(RuntimeException::class);
});
