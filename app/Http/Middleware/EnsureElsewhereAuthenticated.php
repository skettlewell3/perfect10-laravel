<?php

namespace App\Http\Middleware;

use App\Contracts\Auth\CurrentAuthIdentity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureElsewhereAuthenticated
{
    public function __construct(
        private readonly CurrentAuthIdentity $identity
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if ($this->identity->authId() === null) {
            if ($request->expectsJson()) {
                abort(401, 'Authentication is required.');
            }

            return redirect()->route('elsewhere.auth.redirect');
        }

        return $next($request);
    }
}
