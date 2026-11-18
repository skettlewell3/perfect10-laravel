<?php

namespace App\Http\Middleware;

use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\Perfect10ShellContextService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $currentProfile = app(CurrentProfileService::class)
            ->profile();

        $requestedFlavour = $request->route('flavour');

        $hasFlavourContext = is_string($requestedFlavour)
            || $request->routeIs('perfect10.profile');

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'auth' => [
                'user' => $request->user(),
            ],

            'perfect10Profile' => $currentProfile
                ? [
                    'profile_id' => $currentProfile->profile_id,
                    'display_name' => $currentProfile->display_name,
                ]
                : null,

            'perfect10Shell' => fn () => $hasFlavourContext
                ? [
                    'profileTicker' => app(
                        Perfect10ShellContextService::class
                    )->profileTicker(),
                ]
                : null,
        ];
    }
}
