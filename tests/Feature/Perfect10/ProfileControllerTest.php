<?php

use App\Models\Perfect10\ClubProfile;
use App\Models\Perfect10\Profile;
use App\Models\Perfect10\UserProfile;
use App\Services\Perfect10\ClubManagementService;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use App\Services\Perfect10\Perfect10ShellContextService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Profile controller tests require in-memory SQLite.'
        );
    }

    Schema::create('profiles', function (Blueprint $table) {
        $table->string('profile_id')->primary();
        $table->string('profile_type');
        $table->string('display_name');
        $table->timestamps();
    });

    Schema::create('user_profiles', function (Blueprint $table) {
        $table->string('auth_id')->primary();
        $table->string('profile_id');
        $table->string('username');
        $table->timestamps();
    });

    Schema::create('club_profiles', function (Blueprint $table) {
        $table->string('profile_id')->primary();
        $table->string('club_name');
        $table->string('club_code')->unique();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('club_memberships', function (Blueprint $table) {
        $table->string('club_id');
        $table->string('profile_id');
        $table->string('club_role');
        $table->timestamp('created_at')->nullable();
    });

    $shellContext = Mockery::mock(
        Perfect10ShellContextService::class
    );

    $shellContext
        ->shouldReceive('profileTicker')
        ->andReturn([
            'tickerStageLabel' => null,
            'global' => null,
            'flavour' => null,
            'campaign' => null,
            'stage' => null,
        ]);

    $this->app->instance(
        Perfect10ShellContextService::class,
        $shellContext
    );
});

/**
 * Return the available prediction flavours.
 */
function profileControllerFlavours()
{
    return collect([
        [
            'flavour_id' => 1,
            'flavour_name' => 'Premier League',
            'flavour_code' => 'EPL',
            'is_default' => true,
            'format_code' => 'GWK',
        ],
        [
            'flavour_id' => 2,
            'flavour_name' => 'World Cup',
            'flavour_code' => 'WC',
            'is_default' => false,
            'format_code' => 'PFX',
        ],
    ]);
}

/**
 * Mock the shared request context for controller behaviour tests.
 */
function profileControllerMockContext(
    string $flavourCode = 'EPL'
): void {
    $flavours = profileControllerFlavours();

    $selected = $flavours->firstWhere(
        'flavour_code',
        $flavourCode
    );

    $context = Mockery::mock(
        Perfect10RequestContext::class
    );

    $context
        ->shouldReceive('flavours')
        ->zeroOrMoreTimes()
        ->andReturn($flavours);

    $context
        ->shouldReceive('selectedFlavourData')
        ->once()
        ->andReturn($selected);

    app()->instance(
        Perfect10RequestContext::class,
        $context
    );
}

/**
 * Create a test user and return its Eloquent models.
 *
 * @return array{0: Profile, 1: UserProfile}
 */
function profileControllerCreateUser(): array
{
    $profileId = '11111111-1111-4111-8111-111111111111';
    $authId = '22222222-2222-4222-8222-222222222222';

    DB::table('profiles')->insert([
        'profile_id' => $profileId,
        'profile_type' => 'user',
        'display_name' => 'Test Player',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('user_profiles')->insert([
        'auth_id' => $authId,
        'profile_id' => $profileId,
        'username' => 'testplayer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $profile = Profile::query()
        ->where('profile_id', $profileId)
        ->firstOrFail();

    $userProfile = UserProfile::query()
        ->where('auth_id', $authId)
        ->firstOrFail();

    return [$profile, $userProfile];
}

/**
 * Mock the currently authenticated Perfect10 profile.
 */
function profileControllerMockCurrentProfile(
    ?Profile $profile,
    ?UserProfile $userProfile = null
): void {
    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profile')
        ->andReturn($profile);

    $currentProfile
        ->shouldReceive('userProfile')
        ->andReturn($userProfile);

    $currentProfile
        ->shouldReceive('profileId')
        ->andReturn($profile?->profile_id);

    app()->instance(
        CurrentProfileService::class,
        $currentProfile
    );
}

test('profile page returns user details for both prediction formats', function (
    string $flavourCode,
    string $formatCode
) {
    profileControllerMockContext($flavourCode);

    [$profile, $userProfile] = profileControllerCreateUser();

    profileControllerMockCurrentProfile(
        $profile,
        $userProfile
    );

    $response = $this->get(
        route('perfect10.profile')
    );

    $response->assertOk();

    $response->assertInertia(function (Assert $page) use (
        $flavourCode,
        $formatCode
    ) {
        $page
            ->component('perfect10/profile')
            ->where('flavour.flavour_code', $flavourCode)
            ->where('flavour.format_code', $formatCode)
            ->where(
                'profile.profile_id',
                '11111111-1111-4111-8111-111111111111'
            )
            ->where('profile.display_name', 'Test Player')
            ->where('profile.username', 'testplayer')
            ->has('profile.created_at')
            ->where('ownedClub', null)
            ->etc();
    });
})->with([
    'gameweek format' => ['EPL', 'GWK'],
    'per fixture format' => ['WC', 'PFX'],
]);

test('profile route retains the last selected flavour', function (
    string $flavourCode,
    string $formatCode
) {
    $flavours = profileControllerFlavours();

    $flavourService = Mockery::mock(
        FlavourContextService::class
    );

    $flavourService
        ->shouldReceive('availableFlavours')
        ->once()
        ->andReturn($flavours);

    $flavourService
        ->shouldReceive('selectFlavour')
        ->once()
        ->with($flavours, $flavourCode)
        ->andReturnUsing(
            fn () => $flavours->firstWhere(
                'flavour_code',
                $flavourCode
            )
        );

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    [$profile, $userProfile] = profileControllerCreateUser();

    profileControllerMockCurrentProfile(
        $profile,
        $userProfile
    );

    $response = $this
        ->withSession([
            'perfect10.last_flavour' => $flavourCode,
        ])
        ->get(route('perfect10.profile'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('perfect10/profile')
            ->where('flavour.flavour_code', $flavourCode)
            ->where('flavour.format_code', $formatCode)
            ->etc()
    );

    $this->assertSame(
        $flavourCode,
        session('perfect10.last_flavour')
    );
})->with([
    'remembered EPL' => ['EPL', 'GWK'],
    'remembered WC' => ['WC', 'PFX'],
]);

test('profile page returns an owned club', function () {
    profileControllerMockContext('EPL');

    [$profile, $userProfile] = profileControllerCreateUser();

    profileControllerMockCurrentProfile(
        $profile,
        $userProfile
    );

    $clubId = '33333333-3333-4333-8333-333333333333';

    DB::table('club_profiles')->insert([
        'profile_id' => $clubId,
        'club_name' => 'Test Club',
        'club_code' => 'testclub',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('club_memberships')->insert([
        'club_id' => $clubId,
        'profile_id' => $profile->profile_id,
        'club_role' => 'owner',
        'created_at' => now(),
    ]);

    $response = $this->get(
        route('perfect10.profile')
    );

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('perfect10/profile')
            ->where('ownedClub.profile_id', $clubId)
            ->where('ownedClub.club_name', 'Test Club')
            ->where('ownedClub.club_code', 'testclub')
            ->where('ownedClub.is_active', true)
            ->etc()
    );
});

test('profile page returns not found when the profile is missing', function () {
    profileControllerMockContext('EPL');

    profileControllerMockCurrentProfile(null, null);

    $response = $this->get(
        route('perfect10.profile')
    );

    $response->assertNotFound();
});

test('profile display name can be updated', function () {
    [$profile, $userProfile] = profileControllerCreateUser();

    profileControllerMockCurrentProfile(
        $profile,
        $userProfile
    );

    $response = $this
        ->from('/profile')
        ->patch(
            route('perfect10.profile.update'),
            [
                'display_name' => '  New Player  ',
            ]
        );

    $response->assertRedirect('/profile');

    $response->assertSessionHas(
        'success',
        'Display name updated.'
    );

    expect(
        DB::table('profiles')
            ->where('profile_id', $profile->profile_id)
            ->value('display_name')
    )->toBe('New Player');
});

test('profile display name validation rejects invalid values', function (
    mixed $displayName
) {
    [$profile, $userProfile] = profileControllerCreateUser();

    profileControllerMockCurrentProfile(
        $profile,
        $userProfile
    );

    $response = $this
        ->from('/profile')
        ->patch(
            route('perfect10.profile.update'),
            [
                'display_name' => $displayName,
            ]
        );

    $response->assertRedirect('/profile');

    $response->assertSessionHasErrors([
        'display_name',
    ]);

    expect(
        DB::table('profiles')
            ->where('profile_id', $profile->profile_id)
            ->value('display_name')
    )->toBe('Test Player');
})->with([
    'too short' => ['ab'],
    'too long' => ['A name that exceeds sixteen characters'],
    'missing value' => [null],
]);

test('profile can create a club and redirects to the remembered flavour clubhouse', function (
    string $flavourCode
) {
    profileControllerMockContext($flavourCode);

    [$profile, $userProfile] = profileControllerCreateUser();

    profileControllerMockCurrentProfile(
        $profile,
        $userProfile
    );

    $club = new ClubProfile([
        'profile_id' => '33333333-3333-4333-8333-333333333333',
        'club_name' => 'New Club',
        'club_code' => 'newclub',
    ]);

    $clubManagement = Mockery::mock(
        ClubManagementService::class
    );

    $clubManagement
        ->shouldReceive('createClub')
        ->once()
        ->with($profile->profile_id, 'New Club')
        ->andReturn($club);

    $this->app->instance(
        ClubManagementService::class,
        $clubManagement
    );

    $response = $this
        ->withSession([
            'perfect10.last_flavour' => $flavourCode,
        ])
        ->post(
            route('perfect10.profile.club.store'),
            [
                'club_name' => '  New Club  ',
            ]
        );

    $response->assertRedirect(
        route('clubs.show', [
            'flavour' => strtolower($flavourCode),
            'club' => 'newclub',
        ])
    );
})->with([
    'EPL clubhouse' => ['EPL'],
    'WC clubhouse' => ['WC'],
]);
