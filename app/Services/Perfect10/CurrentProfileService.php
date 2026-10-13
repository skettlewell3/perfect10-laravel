<?php

namespace App\Services\Perfect10;

use App\Contracts\Auth\CurrentAuthIdentity;
use App\Models\Perfect10\Profile;
use App\Models\Perfect10\UserProfile;

class CurrentProfileService
{
    private bool $resolved = false;

    private ?UserProfile $userProfile = null;

    public function __construct(
        private readonly CurrentAuthIdentity $identity
    ) {}

    public function authId(): ?string
    {
        return $this->identity->authId();
    }

    public function userProfile(): ?UserProfile
    {
        if ($this->resolved) {
            return $this->userProfile;
        }

        $this->resolved = true;

        $authId = $this->authId();

        if (! $authId) {
            return null;
        }

        $this->userProfile = UserProfile::query()
            ->with('profile')
            ->where('auth_id', $authId)
            ->first();

        return $this->userProfile;
    }

    public function profile(): ?Profile
    {
        return $this->userProfile()?->profile;
    }

    public function profileId(): ?string
    {
        return $this->profile()?->profile_id;
    }

    public function check(): bool
    {
        return $this->profile() !== null;
    }
}
