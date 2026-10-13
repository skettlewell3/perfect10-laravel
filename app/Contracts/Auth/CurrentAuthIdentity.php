<?php

namespace App\Contracts\Auth;

interface CurrentAuthIdentity
{
    public function authId(): ?string;
}
