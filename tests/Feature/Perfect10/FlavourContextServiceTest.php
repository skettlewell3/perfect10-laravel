<?php

use App\Models\Perfect10\Campaign;
use App\Models\Perfect10\Competition;
use App\Models\Perfect10\Flavour;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

beforeEach(function () {
    Schema::create('prediction_formats', function (Blueprint $table) {
        $table->id('format_id');
        $table->string('format_code');
    });

    Schema::create('flavours', function (Blueprint $table) {
        $table->id('flavour_id');
        $table->string('flavour_code');
        $table->string('flavour_name');
        $table->boolean('is_active')->default(true);
        $table->boolean('is_default')->default(false);
        $table->unsignedBigInteger('format_id');
        $table->timestamps();
    });

    Schema::create('competitions', function (Blueprint $table) {
        $table->id('competition_id');
        $table->string('competition_code');
        $table->string('competition_name');
        $table->timestamps();
    });

    Schema::create('flavour_competitions', function (Blueprint $table) {
        $table->unsignedBigInteger('flavour_id');
        $table->unsignedBigInteger('competition_id');
    });

    Schema::create('campaigns', function (Blueprint $table) {
        $table->id('campaign_id');
        $table->unsignedBigInteger('competition_id');
        $table->string('label');
        $table->string('code');
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });
});

test('one flavour resolves to one competition', function () {
    $flavour = Flavour::create([
        'flavour_code' => 'EPL',
        'flavour_name' => 'Premier League',
        'is_active' => true,
        'is_default' => true,
        'format_id' => 1,
    ]);

    $competition = Competition::create([
        'competition_code' => 'EPL',
        'competition_name' => 'English Premier League',
    ]);

    $flavour->competitionLink()->attach($competition->competition_id);

    $service = app(FlavourContextService::class);

    $resolved = $service->competitionFor($flavour);

    expect($resolved->competition_id)
        ->toBe($competition->competition_id);
});

test('missing competition throws', function () {
    $flavour = Flavour::create([
        'flavour_code' => 'EPL',
        'flavour_name' => 'Premier League',
        'is_active' => true,
        'is_default' => true,
        'format_id' => 1,
    ]);

    $service = app(FlavourContextService::class);

    expect(fn () => $service->competitionFor($flavour))
        ->toThrow(RuntimeException::class);
});

test('multiple competitions throws', function () {
    $flavour = Flavour::create([
        'flavour_code' => 'EPL',
        'flavour_name' => 'Premier League',
        'is_active' => true,
        'is_default' => true,
        'format_id' => 1,
    ]);

    $competitionA = Competition::create([
        'competition_code' => 'EPL',
        'competition_name' => 'English Premier League',
    ]);

    $competitionB = Competition::create([
        'competition_code' => 'UCL',
        'competition_name' => 'UEFA Champions League',
    ]);

    $flavour->competitionLink()->attach([
        $competitionA->competition_id,
        $competitionB->competition_id,
    ]);

    $service = app(FlavourContextService::class);

    expect(fn () => $service->competitionFor($flavour))
        ->toThrow(RuntimeException::class);
});

test('exactly one active campaign resolves', function () {
    $flavour = Flavour::create([
        'flavour_code' => 'EPL',
        'flavour_name' => 'Premier League',
        'is_active' => true,
        'is_default' => true,
        'format_id' => 1,
    ]);

    $competition = Competition::create([
        'competition_code' => 'EPL',
        'competition_name' => 'English Premier League',
    ]);

    $flavour->competitionLink()->attach($competition->competition_id);

    $campaign = Campaign::create([
        'competition_id' => $competition->competition_id,
        'label' => 'Premier League 26/27',
        'code' => 'EPL2627',
        'is_active' => true,
    ]);

    $service = app(FlavourContextService::class);

    $resolved = $service->activeCampaignFor($flavour);

    expect($resolved->campaign_id)
        ->toBe($campaign->campaign_id);
});

test('zero active campaigns throws', function () {
    $flavour = Flavour::create([
        'flavour_code' => 'EPL',
        'flavour_name' => 'Premier League',
        'is_active' => true,
        'is_default' => true,
        'format_id' => 1,
    ]);

    $competition = Competition::create([
        'competition_code' => 'EPL',
        'competition_name' => 'English Premier League',
    ]);

    $flavour->competitionLink()->attach($competition->competition_id);

    Campaign::create([
        'competition_id' => $competition->competition_id,
        'label' => 'Premier League 25/26',
        'code' => 'EPL2526',
        'is_active' => false,
    ]);

    $service = app(FlavourContextService::class);

    expect(fn () => $service->activeCampaignFor($flavour))
        ->toThrow(RuntimeException::class);
});

test('multiple active campaigns throws', function () {
    $flavour = Flavour::create([
        'flavour_code' => 'EPL',
        'flavour_name' => 'Premier League',
        'is_active' => true,
        'is_default' => true,
        'format_id' => 1,
    ]);

    $competition = Competition::create([
        'competition_code' => 'EPL',
        'competition_name' => 'English Premier League',
    ]);

    $flavour->competitionLink()->attach($competition->competition_id);

    Campaign::create([
        'competition_id' => $competition->competition_id,
        'label' => 'Premier League 26/27',
        'code' => 'EPL2627',
        'is_active' => true,
    ]);

    Campaign::create([
        'competition_id' => $competition->competition_id,
        'label' => 'Premier League 27/28',
        'code' => 'EPL2728',
        'is_active' => true,
    ]);

    $service = app(FlavourContextService::class);

    expect(fn () => $service->activeCampaignFor($flavour))
        ->toThrow(RuntimeException::class);
});
