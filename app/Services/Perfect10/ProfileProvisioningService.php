<?php

namespace App\Services\Perfect10;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProfileProvisioningService
{
    public function provision(
        string $authId,
        string $username
    ): string {
        /*
         * The PostgreSQL function is responsible for:
         *
         * - Returning an existing profile.
         * - Creating a new profile when necessary.
         * - Enforcing username uniqueness.
         * - Preventing duplicate provisioning.
         *
         * The function runs within PostgreSQL's transaction
         * handling, so a failed insert is rolled back.
         */
        $result = DB::connection('pgsql')->selectOne(
            'SELECT public.provision_elsewhere_profile(
                CAST(? AS uuid),
                ?
            ) AS profile_id',
            [
                $authId,
                $username,
            ]
        );

        $profileId = $result?->profile_id ?? null;

        if (! is_string($profileId) || $profileId === '') {
            throw new RuntimeException(
                'Perfect10 profile provisioning returned no profile ID.'
            );
        }

        return $profileId;
    }
}
