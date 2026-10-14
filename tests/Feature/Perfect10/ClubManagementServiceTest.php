<?php

use App\Models\Perfect10\ClubProfile;
use App\Services\Perfect10\ClubManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    if (
        DB::connection()->getDriverName() !== 'sqlite' ||
        DB::connection()->getDatabaseName() !== ':memory:'
    ) {
        throw new RuntimeException(
            'Club management tests require in-memory SQLite.'
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
        $table->string('club_code');
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
            $table
                ->string('request_id')
                ->primary()
                ->default('generated-request');

            $table->string('club_id');
            $table->string('owner_profile_id');
            $table->string('target_profile_id');
            $table->string('status');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        }
    );

    $this->service = app(ClubManagementService::class);
});

function createClubTestProfile(
    string $profileId,
    string $displayName
): void {
    DB::table('profiles')->insert([
        'profile_id' => $profileId,
        'profile_type' => 'user',
        'display_name' => $displayName,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createClubTestClub(
    string $clubId,
    string $ownerProfileId
): ClubProfile {
    DB::table('club_profiles')->insert([
        'profile_id' => $clubId,
        'club_name' => 'Test Club',
        'club_code' => 'testclub',
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

function addClubTestMembership(
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

test('owner can change a member role', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('member-profile', 'Member');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    $this->service->changeMemberRole(
        $club,
        'owner-profile',
        'member-profile',
        'captain'
    );

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
        'club_role' => 'captain',
    ]);
});

test('captain cannot change member roles', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('captain-profile', 'Captain');
    createClubTestProfile('member-profile', 'Member');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'captain-profile',
        'captain'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    expect(fn () => $this->service->changeMemberRole(
        $club,
        'captain-profile',
        'member-profile',
        'captain'
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
        'club_role' => 'member',
    ]);
});

test('recipient can accept a pending club invite', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('member-profile', 'Member');

    createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    DB::table('club_invites')->insert([
        'invite_id' => 'invite-1',
        'club_id' => 'club-profile',
        'sender_profile_id' => 'owner-profile',
        'recipient_profile_id' => 'member-profile',
        'invite_status' => 'pending',
        'created_at' => now(),
    ]);

    $this->service->acceptInvite(
        'member-profile',
        'invite-1'
    );

    $this->assertDatabaseHas('club_invites', [
        'invite_id' => 'invite-1',
        'invite_status' => 'accepted',
    ]);

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
        'club_role' => 'member',
    ]);
});

test('recipient can decline a pending club invite', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('member-profile', 'Member');

    createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    DB::table('club_invites')->insert([
        'invite_id' => 'invite-1',
        'club_id' => 'club-profile',
        'sender_profile_id' => 'owner-profile',
        'recipient_profile_id' => 'member-profile',
        'invite_status' => 'pending',
        'created_at' => now(),
    ]);

    $this->service->declineInvite(
        'member-profile',
        'invite-1'
    );

    $this->assertDatabaseHas('club_invites', [
        'invite_id' => 'invite-1',
        'invite_status' => 'declined',
    ]);

    $this->assertDatabaseMissing('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
    ]);
});

test('only the owner can request an ownership transfer', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('captain-profile', 'Captain');
    createClubTestProfile('member-profile', 'Member');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'captain-profile',
        'captain'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    expect(fn () => $this->service->requestOwnershipTransfer(
        $club,
        'captain-profile',
        'member-profile'
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseMissing(
        'club_ownership_transfer_requests',
        [
            'club_id' => 'club-profile',
        ]
    );
});

test('owner can request transfer to an existing member', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('member-profile', 'Member');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    $this->service->requestOwnershipTransfer(
        $club,
        'owner-profile',
        'member-profile'
    );

    $this->assertDatabaseHas(
        'club_ownership_transfer_requests',
        [
            'club_id' => 'club-profile',
            'owner_profile_id' => 'owner-profile',
            'target_profile_id' => 'member-profile',
            'status' => 'pending',
        ]
    );
});

test('ownership cannot be transferred to a non member', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('other-profile', 'Other');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    expect(fn () => $this->service->requestOwnershipTransfer(
        $club,
        'owner-profile',
        'other-profile'
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseMissing(
        'club_ownership_transfer_requests',
        [
            'club_id' => 'club-profile',
        ]
    );
});

test('accepting ownership transfer swaps club ownership', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('member-profile', 'Member');

    createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    DB::table(
        'club_ownership_transfer_requests'
    )->insert([
        'request_id' => 'request-1',
        'club_id' => 'club-profile',
        'owner_profile_id' => 'owner-profile',
        'target_profile_id' => 'member-profile',
        'status' => 'pending',
        'created_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->service->acceptOwnershipTransfer(
        'member-profile',
        'request-1'
    );

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'owner-profile',
        'club_role' => 'member',
    ]);

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
        'club_role' => 'owner',
    ]);

    $this->assertDatabaseHas(
        'club_ownership_transfer_requests',
        [
            'request_id' => 'request-1',
            'status' => 'accepted',
        ]
    );
});

test('target can decline ownership transfer', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('member-profile', 'Member');

    createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    DB::table(
        'club_ownership_transfer_requests'
    )->insert([
        'request_id' => 'request-1',
        'club_id' => 'club-profile',
        'owner_profile_id' => 'owner-profile',
        'target_profile_id' => 'member-profile',
        'status' => 'pending',
        'created_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->service->declineOwnershipTransfer(
        'member-profile',
        'request-1'
    );

    $this->assertDatabaseHas(
        'club_ownership_transfer_requests',
        [
            'request_id' => 'request-1',
            'status' => 'declined',
        ]
    );

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'owner-profile',
        'club_role' => 'owner',
    ]);

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
        'club_role' => 'member',
    ]);
});

test('captain can remove an ordinary member', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('captain-profile', 'Captain');
    createClubTestProfile('member-profile', 'Member');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'captain-profile',
        'captain'
    );

    addClubTestMembership(
        'club-profile',
        'member-profile',
        'member'
    );

    $this->service->removeMember(
        $club,
        'captain-profile',
        'member-profile'
    );

    $this->assertDatabaseMissing('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'member-profile',
    ]);
});

test('club owner cannot be removed', function () {
    createClubTestProfile('owner-profile', 'Owner');
    createClubTestProfile('captain-profile', 'Captain');

    $club = createClubTestClub(
        'club-profile',
        'owner-profile'
    );

    addClubTestMembership(
        'club-profile',
        'owner-profile',
        'owner'
    );

    addClubTestMembership(
        'club-profile',
        'captain-profile',
        'captain'
    );

    expect(fn () => $this->service->removeMember(
        $club,
        'captain-profile',
        'owner-profile'
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseHas('club_memberships', [
        'club_id' => 'club-profile',
        'profile_id' => 'owner-profile',
        'club_role' => 'owner',
    ]);
});
