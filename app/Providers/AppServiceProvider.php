<?php

namespace App\Providers;

use App\Contracts\Auth\CurrentAuthIdentity;
use App\Services\Auth\DevCurrentAuthIdentity;
use App\Services\Perfect10\CurrentProfileService;
use App\Services\Perfect10\FlavourContextService;
use App\Services\Perfect10\Perfect10RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CurrentAuthIdentity::class,
            DevCurrentAuthIdentity::class
        );

        $this->app->scoped(
            CurrentProfileService::class
        );

        $this->app->scoped(
            FlavourContextService::class
        );

        $this->app->scoped(
            Perfect10RequestContext::class
        );
    }

    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
