<?php

namespace App\Services\Auth;

use App\Contracts\Auth\CurrentAuthIdentity;
use Illuminate\Support\Str;

class SessionCurrentAuthIdentity implements CurrentAuthIdentity
{
    public function authId(): ?string
    {
        $authId = request()
            ->session()
            ->get('elsewhere_auth_id');

        if (
            ! is_string($authId) ||
            ! Str::isUuid($authId)
        ) {
            return null;
        }

        return $authId;
    }
}
