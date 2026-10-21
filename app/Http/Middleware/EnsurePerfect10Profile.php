<?php

namespace App\Http\Middleware;

use App\Services\Perfect10\CurrentProfileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePerfect10Profile
{
    public function __construct(
        private readonly CurrentProfileService $currentProfile
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        abort_unless(
            $this->currentProfile->check(),
            403,
            'A Perfect10 profile is required to perform this action.'
        );

        return $next($request);
    }
}
