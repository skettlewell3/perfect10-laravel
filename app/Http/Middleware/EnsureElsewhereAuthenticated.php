<?php

namespace App\Http\Middleware;

use App\Contracts\Auth\CurrentAuthIdentity;
use App\Services\Auth\ElsewhereMembershipService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureElsewhereAuthenticated
{
    public function __construct(
        private readonly CurrentAuthIdentity $identity,
        private readonly ElsewhereMembershipService $membership
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if ($this->identity->authId() === null) {
            return $this->unauthenticated($request);
        }

        $result = $this->membership->verify($request);

        if ($result === ElsewhereMembershipService::ACTIVE) {
            return $next($request);
        }

        if ($result === ElsewhereMembershipService::UNAVAILABLE) {
            abort(
                503,
                'Perfect10 cannot currently verify your Elsewhere membership. Please try again later.'
            );
        }

        /*
         * A denied or unusable session must no longer
         * authenticate requests to Perfect10.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($result === ElsewhereMembershipService::DENIED) {
            abort(
                403,
                'Your Elsewhere account is not currently authorised to access Perfect10.'
            );
        }

        return $this->unauthenticated($request);
    }

    private function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(401, 'Authentication is required.');
        }

        return redirect()->route('elsewhere.auth.redirect');
    }
}
