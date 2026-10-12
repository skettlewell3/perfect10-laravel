<?php

namespace App\Services\Auth;

use App\Contracts\Auth\CurrentAuthIdentity;

class DevCurrentAuthIdentity implements CurrentAuthIdentity
{
    public function authId(): ?string
    {
        if (! app()->environment('local')) {
            return null;
        }

        return config('perfect10.dev_auth_id');
    }
}