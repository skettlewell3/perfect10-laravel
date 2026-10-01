<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Stage;
use App\Services\Perfect10\StageContextService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Never create or modify test tables in the live database.
    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'StageContextService tests require in-memory SQLite.'
        );
    }

    Schema::create('stages', function (Blueprint $table) {
        $table->id('stage_id');
        $table->unsignedBigInteger('competition_id');
        $table->unsignedBigInteger('campaign_id');
        $table->string('stage_name');
        $table->string('stage_code');
        $table->integer('order_index');
        $table->boolean('is_active')->default(false);
        $table->boolean('is_live')->default(false);
        $table->boolean('is_finished')->default(false);
    });
});

/**
 * Create stage models for testing selection logic.
 *
 * @param  array<int, array<string, mixed>>  $rows
 * @return Collection<int, Stage>
 */
function makeTestStages(array $rows): Collection
{
    return new Collection(
        array_map(
            fn (array $attributes): Stage => new Stage($attributes),
            $rows
        )
    );
}

test('retrieves competition stages in order', function () {
    DB::table('stages')->insert([
        [
            'stage_id' => 1,
            'competition_id' => 10,
            'campaign_id' => 100,
            'stage_name' => 'Second Stage',
            'stage_code' => 'S2',
            'order_index' => 2,
        ],
        [
            'stage_id' => 2,
            'competition_id' => 10,
            'campaign_id' => 100,
            'stage_name' => 'First Stage',
            'stage_code' => 'S1',
            'order_index' => 1,
        ],
        [
            'stage_id' => 3,
            'competition_id' => 20,
            'campaign_id' => 200,
            'stage_name' => 'Other Competition',
            'stage_code' => 'OTHER',
            'order_index' => 1,
        ],
    ]);

    $competition = new Competition([
        'competition_id' => 10,
    ]);

    $service = app(StageContextService::class);

    $stages = $service->stagesForCompetition($competition);

    expect($stages->pluck('stage_id')->all())
        ->toBe([2, 1]);
});

test('retrieves only stages belonging to the selected campaign', function () {
    DB::table('stages')->insert([
        [
            'stage_id' => 1,
            'competition_id' => 10,
            'campaign_id' => 100,
            'stage_name' => 'GW2',
            'stage_code' => 'GW2',
            'order_index' => 2,
        ],
        [
            'stage_id' => 2,
            'competition_id' => 10,
            'campaign_id' => 100,
            'stage_name' => 'GW1',
            'stage_code' => 'GW1',
            'order_index' => 1,
        ],
        [
            'stage_id' => 3,
            'competition_id' => 10,
            'campaign_id' => 99,
            'stage_name' => 'Previous Season',
            'stage_code' => 'OLD',
            'order_index' => 1,
        ],
        [
            'stage_id' => 4,
            'competition_id' => 20,
            'campaign_id' => 100,
            'stage_name' => 'Other Competition',
            'stage_code' => 'OTHER',
            'order_index' => 1,
        ],
    ]);

    $campaign = new Campaign([
        'campaign_id' => 100,
        'competition_id' => 10,
    ]);

    $service = app(StageContextService::class);

    $stages = $service->stagesForCampaign($campaign);

    expect($stages->pluck('stage_id')->all())
        ->toBe([2, 1]);
});

test('identifies the active stage', function () {
    $stages = makeTestStages([
        [
            'stage_id' => 1,
            'is_active' => false,
        ],
        [
            'stage_id' => 2,
            'is_active' => true,
        ],
        [
            'stage_id' => 3,
            'is_active' => false,
        ],
    ]);

    $service = app(StageContextService::class);

    expect($service->activeStage($stages)?->stage_id)
        ->toBe(2);
});

test('returns null when no stage is active', function () {
    $stages = makeTestStages([
        [
            'stage_id' => 1,
            'is_active' => false,
        ],
    ]);

    $service = app(StageContextService::class);

    expect($service->activeStage($stages))
        ->toBeNull();
});

test('ticker prioritises a live stage', function () {
    $stages = makeTestStages([
        [
            'stage_id' => 1,
            'order_index' => 1,
            'is_finished' => true,
        ],
        [
            'stage_id' => 2,
            'order_index' => 2,
            'is_live' => true,
            'is_active' => true,
        ],
        [
            'stage_id' => 3,
            'order_index' => 3,
            'is_active' => true,
        ],
    ]);

    $service = app(StageContextService::class);

    expect($service->tickerStage($stages)?->stage_id)
        ->toBe(2);
});

test('ticker selects the most recently finished stage when none is live', function () {
    $stages = makeTestStages([
        [
            'stage_id' => 1,
            'order_index' => 1,
            'is_finished' => true,
        ],
        [
            'stage_id' => 2,
            'order_index' => 3,
            'is_active' => true,
        ],
        [
            'stage_id' => 3,
            'order_index' => 2,
            'is_finished' => true,
        ],
    ]);

    $service = app(StageContextService::class);

    expect($service->tickerStage($stages)?->stage_id)
        ->toBe(3);
});

test('ticker falls back to the active stage', function () {
    $stages = makeTestStages([
        [
            'stage_id' => 1,
            'order_index' => 1,
            'is_active' => false,
        ],
        [
            'stage_id' => 2,
            'order_index' => 2,
            'is_active' => true,
        ],
    ]);

    $service = app(StageContextService::class);

    expect($service->tickerStage($stages)?->stage_id)
        ->toBe(2);
});

test('ticker returns null when no suitable stage exists', function () {
    $stages = makeTestStages([
        [
            'stage_id' => 1,
            'order_index' => 1,
            'is_active' => false,
            'is_live' => false,
            'is_finished' => false,
        ],
    ]);

    $service = app(StageContextService::class);

    expect($service->tickerStage($stages))
        ->toBeNull();
});
