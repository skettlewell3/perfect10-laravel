<?php

namespace App\Http\Controllers\Perfect10;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\ClubManagementService;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10RequestContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private CurrentProfileService $currentProfile,
        private Perfect10RequestContext $context,
        private ClubManagementService $clubManagement,
    ) {}

    public function index(): Response
    {
        $flavours = $this->context->flavours();

        $selectedFlavourData = $this->context
            ->selectedFlavourData();

        $userProfile = $this->currentProfile
            ->userProfile();

        $profile = $this->currentProfile
            ->profile();

        abort_if(
            $userProfile === null || $profile === null,
            404,
            'Perfect10 profile not found.'
        );

        $ownedClub = $profile
            ->clubMemberships()
            ->where('club_role', 'owner')
            ->with('club')
            ->first()
            ?->club;

        return Inertia::render('perfect10/profile', [
            'flavours' => $flavours,
            'flavour' => $selectedFlavourData,

            'profile' => [
                'profile_id' => $profile->profile_id,
                'display_name' => $profile->display_name,
                'username' => $userProfile->username,
                'created_at' => $profile->created_at,
            ],

            'ownedClub' => $ownedClub
                ? [
                    'profile_id' => $ownedClub->profile_id,
                    'club_name' => $ownedClub->club_name,
                    'club_code' => $ownedClub->club_code,
                    'is_active' => $ownedClub->is_active,
                ]
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => [
                'required',
                'string',
                'min:3',
                'max:16',
            ],
        ]);

        $profile = $this->currentProfile
            ->profile();

        abort_if(
            $profile === null,
            404,
            'Perfect10 profile not found.'
        );

        $profile->update([
            'display_name' => trim(
                $validated['display_name']
            ),
        ]);

        return back()->with(
            'success',
            'Display name updated.'
        );
    }

    public function createClub(
        Request $request,
    ): RedirectResponse {
        $validated = $request->validate([
            'club_name' => [
                'required',
                'string',
                'min:3',
                'max:16',
            ],
        ]);

        $profileId = $this->currentProfile
            ->profileId();

        abort_if(
            $profileId === null,
            403,
            'Perfect10 profile not found.'
        );

        $club = $this->clubManagement
            ->createClub(
                $profileId,
                trim($validated['club_name'])
            );

        return redirect()->route(
            'clubs.show',
            [
                'flavour' => strtolower(
                    $this->context->selectedFlavourData()['flavour_code']
                ),
                'club' => $club->club_code,
            ]
        );
    }
}