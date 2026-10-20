<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\ClubInvite;
use App\Models\Perfect10\ClubMembership;
use App\Models\Perfect10\ClubOwnershipTransferRequest;
use App\Models\Perfect10\ClubProfile;
use App\Services\Perfect10\ClubManagementService;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\LeaderboardContextService;
use App\Services\Perfect10\LeagueTableContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubsController extends Controller
{
    public function __construct(
        private ClubManagementService $clubManagement,
        private LeaderboardContextService $leaderboardContext,
        private LeagueTableContextService $leagueTableContext,
        private Perfect10RequestContext $context,
    ) {}

    public function index(
        string $flavour,
        CurrentProfileService $currentProfile
    ): Response {
        $flavours = $this->context->flavours();

        $selectedFlavourData = $this->context
            ->selectedFlavourData();

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $memberships = ClubMembership::query()
            ->with('club')
            ->where('profile_id', $profileId)
            ->whereHas('club', function ($query) {
                $query->where('is_deleted', false);
            })
            ->orderBy('created_at')
            ->get()
            ->map(fn (ClubMembership $membership) => [
                'club_id' => $membership->club_id,
                'club_name' => $membership->club->club_name,
                'club_code' => $membership->club->club_code,
                'club_colour' => $membership->club->club_colour,
                'club_role' => $membership->club_role,
                'is_active' => $membership->club->is_active,
                'invite_only' => $membership->club->invite_only,
            ])
            ->values();

        $invites = ClubInvite::query()
            ->with([
                'club',
                'sender',
            ])
            ->where('recipient_profile_id', $profileId)
            ->where('invite_status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ClubInvite $invite) => [
                'invite_id' => $invite->invite_id,
                'club_id' => $invite->club_id,
                'club_name' => $invite->club->club_name,
                'club_code' => $invite->club->club_code,
                'club_colour' => $invite->club->club_colour,
                'sender_name' => $invite->sender->display_name,
                'created_at' => $invite->created_at,
            ])
            ->values();

        $ownershipTransferRequests =
            ClubOwnershipTransferRequest::query()
                ->with([
                    'club',
                    'ownerProfile',
                ])
                ->where('target_profile_id', $profileId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (
                    ClubOwnershipTransferRequest $request
                ) => [
                    'request_id' => $request->request_id,
                    'club_id' => $request->club_id,
                    'club_name' => $request->club->club_name,
                    'club_code' => $request->club->club_code,
                    'owner_profile_id' => $request->owner_profile_id,
                    'owner_display_name' => $request->ownerProfile->display_name,
                    'created_at' => $request->created_at,
                    'expires_at' => $request->expires_at,
                ])
                ->values();

        return Inertia::render('perfect10/clubs', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,
            'memberships' => $memberships,
            'invites' => $invites,
            'ownershipTransferRequests' => $ownershipTransferRequests,
        ]);
    }

    public function show(
        string $flavour,
        ClubProfile $club,
        CurrentProfileService $currentProfile
    ): Response {
        abort_if(
            $club->is_deleted,
            404
        );

        $flavours = $this->context->flavours();

        $selectedFlavourData = $this->context
            ->selectedFlavourData();

        $flavourId = (int) $selectedFlavourData['flavour_id'];

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $club->load([
            'memberships.profile.userProfile',
        ]);

        $currentMembership = $club->memberships
            ->firstWhere('profile_id', $profileId);

        abort_if(
            $currentMembership === null,
            403,
            'You are not a member of this club.'
        );

        $members = $club->memberships
            ->map(fn (ClubMembership $membership) => [
                'member_profile_id' => $membership->profile_id,
                'display_name' => $membership->profile->display_name,
                'username' => $membership->profile->userProfile?->username,
                'club_role' => $membership->club_role,
            ])
            ->values();

        $clubLeaderboard = $this->leaderboardContext
            ->clubLeaderboard(
                $club->profile_id,
                $flavourId
            );

        $actualTable = collect();
        $predictedTable = collect();

        if ($selectedFlavourData['format_code'] === 'GWK') {
            $actualTable = $this->leagueTableContext
                ->actualTable($flavourId);

            $predictedTable = $this->leagueTableContext
                ->predictedTable(
                    $flavourId,
                    $club->profile_id
                );
        }

        return Inertia::render('perfect10/clubhouse', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,

            'club' => [
                'club_id' => $club->profile_id,
                'club_name' => $club->club_name,
                'club_code' => $club->club_code,
                'club_colour' => $club->club_colour,
                'club_role' => $currentMembership->club_role,
                'is_active' => $club->is_active,
                'invite_only' => $club->invite_only,
            ],

            'members' => $members,

            'clubLeaderboard' => $clubLeaderboard,

            'actualTable' => $actualTable,
            'predictedTable' => $predictedTable,

            'permissions' => [
                'can_manage_club' => in_array(
                    $currentMembership->club_role,
                    ['owner', 'captain'],
                    true
                ),
                'can_manage_roles' => $currentMembership->club_role === 'owner',
            ],
        ]);
    }

    public function sendInvite(
        Request $request,
        string $flavour,
        ClubProfile $club,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->sendInvite(
            $club,
            $profileId,
            $validated['username']
        );

        return back();
    }

    public function acceptInvite(
        Request $request,
        string $flavour,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'invite_id' => [
                'required',
                'uuid',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->acceptInvite(
            $profileId,
            $validated['invite_id']
        );

        return back();
    }

    public function declineInvite(
        Request $request,
        string $flavour,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'invite_id' => [
                'required',
                'uuid',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->declineInvite(
            $profileId,
            $validated['invite_id']
        );

        return back();
    }

    public function requestOwnershipTransfer(
        Request $request,
        string $flavour,
        ClubProfile $club,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'target_profile_id' => [
                'required',
                'uuid',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->requestOwnershipTransfer(
            $club,
            $profileId,
            $validated['target_profile_id']
        );

        return back();
    }

    public function acceptOwnershipTransfer(
        Request $request,
        string $flavour,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'request_id' => [
                'required',
                'uuid',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->acceptOwnershipTransfer(
            $profileId,
            $validated['request_id']
        );

        return back();
    }

    public function declineOwnershipTransfer(
        Request $request,
        string $flavour,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'request_id' => [
                'required',
                'uuid',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->declineOwnershipTransfer(
            $profileId,
            $validated['request_id']
        );

        return back();
    }

    public function updateMemberRole(
        Request $request,
        string $flavour,
        ClubProfile $club,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'member_profile_id' => [
                'required',
                'uuid',
            ],
            'role' => [
                'required',
                'in:member,captain',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->changeMemberRole(
            $club,
            $profileId,
            $validated['member_profile_id'],
            $validated['role']
        );

        return back();
    }

    public function removeMember(
        Request $request,
        string $flavour,
        ClubProfile $club,
        CurrentProfileService $currentProfile
    ): RedirectResponse {
        $validated = $request->validate([
            'member_profile_id' => [
                'required',
                'uuid',
            ],
        ]);

        $profileId = $currentProfile->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $this->clubManagement->removeMember(
            $club,
            $profileId,
            $validated['member_profile_id']
        );

        return back();
    }
}
