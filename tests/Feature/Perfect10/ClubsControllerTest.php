<?php

use App\Models\Perfect10\ClubProfile;
use App\Services\Perfect10\ClubManagementService;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\LeaderboardContextService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\Perfect10ShellContextService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
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
            'Clubs controller tests require in-memory SQLite.'
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
        $table->string('club_colour')->nullable();
        $table->boolean('invite_only')->default(true);
        $table->boolean('is_active')->default(true);
        $table->string('created_by')->nullable();
        $table->boolean('is_deleted')->default(false);
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    Schema::create('club_memberships', function (Blueprint $table) {
        $table->string('club_id');
        $table->string('profile_id');
        $table->string('club_role');
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('club_invites', function (Blueprint $table) {
        $table->string('invite_id')->primary();
        $table->string('club_id');
        $table->string('sender_profile_id');
        $table->string('recipient_profile_id');
        $table->string('invite_status');
        $table->timestamp('created_at')->nullable();
    });

    Schema::create(
        'club_ownership_transfer_requests',
        function (Blueprint $table) {
            $table->string('request_id')->primary();
            $table->string('club_id');
            $table->string('owner_profile_id');
            $table->string('target_profile_id');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        }
    );

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

function clubsControllerCreateProfile(
    string $profileId,
    string $displayName,
    ?string $username = null
): void {
    DB::table('profiles')->insert([
        'profile_id' => $profileId,
        'profile_type' => 'user',
        'display_name' => $displayName,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($username !== null) {
        DB::table('user_profiles')->insert([
            'auth_id' => $profileId.'-auth',
            'profile_id' => $profileId,
            'username' => $username,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function clubsControllerCreateClub(
    string $clubId,
    string $ownerProfileId
): ClubProfile {
    DB::table('club_profiles')->insert([
        'profile_id' => $clubId,
        'club_name' => 'Test Club',
        'club_code' => 'testclub',
        'club_colour' => '#123456',
        'invite_only' => true,
        'is_active' => true,
        'created_by' => $ownerProfileId,
        'is_deleted' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ClubProfile::query()
        ->where('profile_id', $clubId)
        ->firstOrFail();
}

function clubsControllerAddMembership(
    string $clubId,
    string $profileId,
    string $role
): void {
    DB::table('club_memberships')->insert([
        'club_id' => $clubId,
        'profile_id' => $profileId,
        'club_role' => $role,
        'created_at' => now(),
    ]);
}

function clubsControllerFlavours(): Collection
{
    return collect([
        [
            'flavour_id' => 1,
            'flavour_name' => 'Premier League',
            'flavour_code' => 'EPL',
            'format_code' => 'GWK',
        ],
        [
            'flavour_id' => 2,
            'flavour_name' => 'World Cup',
            'flavour_code' => 'WC',
            'format_code' => 'PFX',
        ],
    ]);
}

test('clubs index returns memberships invites and ownership requests', function () {
    $currentProfileId = 'current-profile';
    $ownerProfileId = 'owner-profile';
    $clubId = 'club-profile';

    clubsControllerCreateProfile(
        $currentProfileId,
        'Current User',
        'currentuser'
    );

    clubsControllerCreateProfile(
        $ownerProfileId,
        'Club Owner',
        'clubowner'
    );

    clubsControllerCreateClub(
        $clubId,
        $ownerProfileId
    );

    clubsControllerAddMembership(
        $clubId,
        $currentProfileId,
        'member'
    );

    clubsControllerAddMembership(
        $clubId,
        $ownerProfileId,
        'owner'
    );

    DB::table('club_invites')->insert([
        'invite_id' => 'invite-1',
        'club_id' => $clubId,
        'sender_profile_id' => $ownerProfileId,
        'recipient_profile_id' => $currentProfileId,
        'invite_status' => 'pending',
        'created_at' => now(),
    ]);

    DB::table(
        'club_ownership_transfer_requests'
    )->insert([
        'request_id' => 'request-1',
        'club_id' => $clubId,
        'owner_profile_id' => $ownerProfileId,
        'target_profile_id' => $currentProfileId,
        'status' => 'pending',
        'created_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $flavours = clubsControllerFlavours();

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
        ->with($flavours, 'EPL')
        ->andReturn($flavours->first());

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($currentProfileId);

    $currentProfile
        ->shouldReceive('profile')
        ->andReturn(null);

    $this->app->instance(
        CurrentProfileService::class,
        $currentProfile
    );

    $response = $this->get(
        route('clubs', [
            'flavour' => 'EPL',
        ])
    );

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('perfect10/clubs')
            ->has('memberships', 1)
            ->where(
                'memberships.0.club_code',
                'testclub'
            )
            ->where(
                'memberships.0.club_role',
                'member'
            )
            ->has('invites', 1)
            ->where(
                'invites.0.sender_name',
                'Club Owner'
            )
            ->has('ownershipTransferRequests', 1)
            ->where(
                'ownershipTransferRequests.0.owner_display_name',
                'Club Owner'
            )
            ->etc()
    );
});

test('gameweek clubhouse returns league table data', function () {
    $currentProfileId = 'current-profile';
    $clubId = 'club-profile';

    clubsControllerCreateProfile(
        $currentProfileId,
        'Current User',
        'currentuser'
    );

    $club = clubsControllerCreateClub(
        $clubId,
        $currentProfileId
    );

    clubsControllerAddMembership(
        $clubId,
        $currentProfileId,
        'owner'
    );

    $flavours = clubsControllerFlavours();

    $selectedFlavour = $flavours->firstWhere(
        'flavour_code',
        'EPL'
    );

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
        ->with($flavours, 'EPL')
        ->andReturn($selectedFlavour);

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($currentProfileId);

    $currentProfile
        ->shouldReceive('profile')
        ->andReturn(null);

    $this->app->instance(
        CurrentProfileService::class,
        $currentProfile
    );

    $leaderboardService = Mockery::mock(
        LeaderboardContextService::class
    );

    $leaderboardService
        ->shouldReceive('clubLeaderboard')
        ->once()
        ->with($clubId, 1)
        ->andReturn(collect([
            [
                'rank' => 1,
                'profile_id' => $currentProfileId,
                'display_name' => 'Current User',
            ],
        ]));

    $this->app->instance(
        LeaderboardContextService::class,
        $leaderboardService
    );

    $leagueTableService = Mockery::mock(
        LeagueTableContextService::class
    );

    $actualTable = collect([
        [
            'team_id' => 1,
            'team_name' => 'Actual Team',
            'short_code' => 'ACT',
            'table_type' => 'actual',
            'pos' => 1,
            'played' => 1,
            'won' => 1,
            'drawn' => 0,
            'lost' => 0,
            'gf' => 2,
            'ga' => 0,
            'gd' => 2,
            'points' => 3,
        ],
    ]);

    $predictedTable = collect([
        [
            'team_id' => 1,
            'team_name' => 'Predicted Team',
            'short_code' => 'PRD',
            'table_type' => 'predicted',
            'pos' => 1,
            'played' => 1,
            'won' => 1,
            'drawn' => 0,
            'lost' => 0,
            'gf' => 3,
            'ga' => 1,
            'gd' => 2,
            'points' => 3,
        ],
    ]);

    $leagueTableService
        ->shouldReceive('actualTable')
        ->once()
        ->with(1)
        ->andReturn($actualTable);

    $leagueTableService
        ->shouldReceive('predictedTable')
        ->once()
        ->with(1, $clubId)
        ->andReturn($predictedTable);

    $this->app->instance(
        LeagueTableContextService::class,
        $leagueTableService
    );

    $response = $this->get(
        route('clubs.show', [
            'flavour' => 'EPL',
            'club' => $club,
        ])
    );

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('perfect10/clubhouse')
            ->where('club.club_code', 'testclub')
            ->where('club.club_role', 'owner')
            ->has('members', 1)
            ->has('clubLeaderboard', 1)
            ->has('actualTable', 1)
            ->where(
                'actualTable.0.table_type',
                'actual'
            )
            ->has('predictedTable', 1)
            ->where(
                'predictedTable.0.table_type',
                'predicted'
            )
            ->where(
                'permissions.can_manage_club',
                true
            )
            ->where(
                'permissions.can_manage_roles',
                true
            )
            ->etc()
    );
});

test('per fixture clubhouse does not load league tables', function () {
    $currentProfileId = 'current-profile';
    $clubId = 'club-profile';

    clubsControllerCreateProfile(
        $currentProfileId,
        'Current User',
        'currentuser'
    );

    $club = clubsControllerCreateClub(
        $clubId,
        $currentProfileId
    );

    clubsControllerAddMembership(
        $clubId,
        $currentProfileId,
        'owner'
    );

    $flavours = clubsControllerFlavours();

    $selectedFlavour = $flavours->firstWhere(
        'flavour_code',
        'WC'
    );

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
        ->with($flavours, 'WC')
        ->andReturn($selectedFlavour);

    $this->app->instance(
        FlavourContextService::class,
        $flavourService
    );

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($currentProfileId);

    $currentProfile
        ->shouldReceive('profile')
        ->andReturn(null);

    $this->app->instance(
        CurrentProfileService::class,
        $currentProfile
    );

    $leaderboardService = Mockery::mock(
        LeaderboardContextService::class
    );

    $leaderboardService
        ->shouldReceive('clubLeaderboard')
        ->once()
        ->with($clubId, 2)
        ->andReturn(collect());

    $this->app->instance(
        LeaderboardContextService::class,
        $leaderboardService
    );

    $leagueTableService = Mockery::mock(
        LeagueTableContextService::class
    );

    $leagueTableService->shouldNotReceive(
        'actualTable'
    );

    $leagueTableService->shouldNotReceive(
        'predictedTable'
    );

    $this->app->instance(
        LeagueTableContextService::class,
        $leagueTableService
    );

    $response = $this->get(
        route('clubs.show', [
            'flavour' => 'WC',
            'club' => $club,
        ])
    );

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('perfect10/clubhouse')
            ->has('actualTable', 0)
            ->has('predictedTable', 0)
            ->etc()
    );
});

test('remove member endpoint delegates to club management service', function () {
    $currentProfileId =
        '11111111-1111-4111-8111-111111111111';

    $memberProfileId =
        '22222222-2222-4222-8222-222222222222';

    $clubId =
        '33333333-3333-4333-8333-333333333333';

    clubsControllerCreateProfile(
        $currentProfileId,
        'Current User',
        'currentuser'
    );

    clubsControllerCreateProfile(
        $memberProfileId,
        'Member',
        'member'
    );

    $club = clubsControllerCreateClub(
        $clubId,
        $currentProfileId
    );

    clubsControllerAddMembership(
        $clubId,
        $currentProfileId,
        'owner'
    );

    clubsControllerAddMembership(
        $clubId,
        $memberProfileId,
        'member'
    );

    $currentProfile = Mockery::mock(
        CurrentProfileService::class
    );

    $currentProfile
        ->shouldReceive('profileId')
        ->once()
        ->andReturn($currentProfileId);

    $currentProfile
        ->shouldReceive('profile')
        ->andReturn(null);

    $this->app->instance(
        CurrentProfileService::class,
        $currentProfile
    );

    $clubManagement = Mockery::mock(
        ClubManagementService::class
    );

    $clubManagement
        ->shouldReceive('removeMember')
        ->once()
        ->withArgs(function (
            ClubProfile $receivedClub,
            string $receivedCurrentProfileId,
            string $receivedMemberProfileId
        ) use (
            $clubId,
            $currentProfileId,
            $memberProfileId
        ) {
            return
                $receivedClub->profile_id === $clubId &&
                $receivedCurrentProfileId === $currentProfileId &&
                $receivedMemberProfileId === $memberProfileId;
        });

    $this->app->instance(
        ClubManagementService::class,
        $clubManagement
    );

    $response = $this
        ->from('/epl/clubs/testclub')
        ->delete(
            route('clubs.members.remove', [
                'flavour' => 'EPL',
                'club' => $club,
            ]),
            [
                'member_profile_id' => $memberProfileId,
            ]
        );

    $response->assertRedirect(
        '/epl/clubs/testclub'
    );
});
