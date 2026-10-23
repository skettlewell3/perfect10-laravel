<?php

use App\Services\Perfect10\MatchFormContextService;

/**
 * Build a fixture with the fields required by MatchFormContextService.
 *
 * @return array<string, mixed>
 */
function formTestFixture(
    int $fixtureId,
    int $homeTeamId,
    int $awayTeamId,
    string $kickoff,
    int $competitionId = 1
): array {
    return [
        'fixture_id' => $fixtureId,
        'home_team_id' => $homeTeamId,
        'away_team_id' => $awayTeamId,
        'kickoff_at' => $kickoff,
        'competition_id' => $competitionId,
    ];
}

/**
 * @param  list<array<string, mixed>>  $fixtures
 * @return list<int>
 */
function formTestFixtureIds(array $fixtures): array
{
    return array_map(
        fn (array $fixture): int => (int) $fixture['fixture_id'],
        $fixtures
    );
}

test('selects the five previous fixtures newest first and two future fixtures oldest first', function () {
    $service = new MatchFormContextService;

    $fixtures = [];

    for ($day = 1; $day <= 12; $day++) {
        $fixtures[] = formTestFixture(
            $day,
            10,
            20 + $day,
            sprintf('2026-10-%02d 15:00:00', $day)
        );
    }

    // Deliberately reverse the input to verify chronological sorting.
    $fixtures = array_reverse($fixtures);

    $form = $service->forTeam(
        $fixtures,
        teamId: 10,
        anchorFixtureId: 8
    );

    expect(formTestFixtureIds($form['past']))
        ->toBe([7, 6, 5, 4, 3]);

    expect(formTestFixtureIds($form['future']))
        ->toBe([9, 10]);
});

test('selects fixtures where the team plays either home or away', function () {
    $service = new MatchFormContextService;

    $fixtures = [
        formTestFixture(1, 10, 20, '2026-10-01 15:00:00'),
        formTestFixture(2, 30, 10, '2026-10-05 15:00:00'),
        formTestFixture(3, 10, 40, '2026-10-10 15:00:00'),
        formTestFixture(4, 50, 10, '2026-10-15 15:00:00'),
        formTestFixture(5, 60, 70, '2026-10-18 15:00:00'),
    ];

    $form = $service->forTeam(
        $fixtures,
        teamId: 10,
        anchorFixtureId: 3
    );

    expect(formTestFixtureIds($form['past']))
        ->toBe([2, 1]);

    expect(formTestFixtureIds($form['future']))
        ->toBe([4]);
});

test('uses the anchor fixture rather than the current date', function () {
    $service = new MatchFormContextService;

    $fixtures = [
        formTestFixture(100, 10, 20, '2025-08-01 15:00:00'),
        formTestFixture(101, 10, 30, '2025-08-08 15:00:00'),
        formTestFixture(102, 40, 10, '2025-08-15 15:00:00'),
        formTestFixture(103, 10, 50, '2025-08-22 15:00:00'),
    ];

    $form = $service->forTeam(
        $fixtures,
        teamId: 10,
        anchorFixtureId: 101
    );

    expect(formTestFixtureIds($form['past']))
        ->toBe([100]);

    expect(formTestFixtureIds($form['future']))
        ->toBe([102, 103]);
});

test('returns empty arrays when the anchor fixture is missing', function () {
    $service = new MatchFormContextService;

    $fixtures = [
        formTestFixture(1, 10, 20, '2026-10-01 15:00:00'),
        formTestFixture(2, 10, 30, '2026-10-02 15:00:00'),
    ];

    $form = $service->forTeam(
        $fixtures,
        teamId: 10,
        anchorFixtureId: 999
    );

    expect($form)->toBe([
        'past' => [],
        'future' => [],
    ]);
});

test('forMatch independently selects form for both participating teams', function () {
    $service = new MatchFormContextService;

    $fixtures = [
        formTestFixture(1, 10, 30, '2026-10-01 15:00:00'),
        formTestFixture(2, 40, 20, '2026-10-02 15:00:00'),
        formTestFixture(3, 10, 20, '2026-10-10 15:00:00'),
        formTestFixture(4, 50, 10, '2026-10-15 15:00:00'),
        formTestFixture(5, 20, 60, '2026-10-16 15:00:00'),
    ];

    $form = $service->forMatch(
        $fixtures,
        $fixtures[2]
    );

    expect(formTestFixtureIds($form['home']['past']))
        ->toBe([1]);

    expect(formTestFixtureIds($form['home']['future']))
        ->toBe([4]);

    expect(formTestFixtureIds($form['away']['past']))
        ->toBe([2]);

    expect(formTestFixtureIds($form['away']['future']))
        ->toBe([5]);
});

test('accepts custom previous and future fixture limits', function () {
    $service = new MatchFormContextService;

    $fixtures = [];

    for ($day = 1; $day <= 10; $day++) {
        $fixtures[] = formTestFixture(
            $day,
            10,
            20,
            sprintf('2026-10-%02d 15:00:00', $day)
        );
    }

    $form = $service->forTeam(
        $fixtures,
        teamId: 10,
        anchorFixtureId: 5,
        pastCount: 2,
        futureCount: 3
    );

    expect(formTestFixtureIds($form['past']))
        ->toBe([4, 3]);

    expect(formTestFixtureIds($form['future']))
        ->toBe([6, 7, 8]);
});

test('preserves fixtures from multiple competitions when supplied', function () {
    $service = new MatchFormContextService;

    $fixtures = [
        formTestFixture(1, 10, 20, '2026-10-01 15:00:00', 1),
        formTestFixture(2, 10, 30, '2026-10-05 20:00:00', 2),
        formTestFixture(3, 10, 40, '2026-10-10 15:00:00', 1),
        formTestFixture(4, 50, 10, '2026-10-14 20:00:00', 3),
        formTestFixture(5, 10, 60, '2026-10-18 15:00:00', 1),
    ];

    $form = $service->forTeam(
        $fixtures,
        teamId: 10,
        anchorFixtureId: 3
    );

    expect(formTestFixtureIds($form['past']))
        ->toBe([2, 1]);

    expect(formTestFixtureIds($form['future']))
        ->toBe([4, 5]);

    expect($form['past'][0]['competition_id'])->toBe(2);
    expect($form['future'][0]['competition_id'])->toBe(3);
});
