<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Perfect10\ProfileProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElsewhereProfileProvisioningController extends Controller
{
    public function __invoke(
        Request $request,
        ProfileProvisioningService $provisioning
    ): JsonResponse {
        /*
         * This endpoint must be protected by the dedicated
         * Elsewhere provisioning signature middleware.
         *
         * These fields are supplied by the trusted Elsewhere
         * backend, never directly by a browser.
         */
        $validated = $request->validate([
            'auth_id' => [
                'required',
                'uuid',
            ],
            'username' => [
                'required',
                'string',
                'min:1',
                'max:16',
            ],
        ]);

        $profileId = $provisioning->provision(
            $validated['auth_id'],
            $validated['username']
        );

        return response()->json([
            'profile_id' => $profileId,
            'provisioned' => true,
        ]);
    }
}
