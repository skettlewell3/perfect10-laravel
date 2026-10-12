<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\ClubMembership;
use App\Models\Perfect10\ClubProfile;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FlavourContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClubsController extends Controller
{
    public function __construct(
        private FlavourContextService $flavourContext,
    ) {}

    public function index(
        Request $request,
        CurrentProfileService $currentProfile
    ): Response {
        $flavours = $this->flavourContext->availableFlavours();

        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );

        $selectedFlavourData = $this->flavourContext
            ->selectFlavour(
                $flavours,
                (string) $request->route('flavour')
            );

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

        return Inertia::render('perfect10/clubs', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,
            'memberships' => $memberships,
        ]);
    }

    public function show(
        Request $request,
        string $flavour,
        ClubProfile $club,
        CurrentProfileService $currentProfile
    ): Response {
        abort_if(
            $club->is_deleted,
            404
        );
    
        $flavours = $this->flavourContext->availableFlavours();
    
        abort_if(
            $flavours->isEmpty(),
            404,
            'No active Perfect10 flavour is available.'
        );
    
        $selectedFlavourData = $this->flavourContext
            ->selectFlavour(
                $flavours,
                $flavour
            );
        
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
}