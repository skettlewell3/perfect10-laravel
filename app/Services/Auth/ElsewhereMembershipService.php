<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ElsewhereMembershipService
{
    public const ACTIVE = 'active';

    public const DENIED = 'denied';

    public const UNAUTHENTICATED = 'unauthenticated';

    public const UNAVAILABLE = 'unavailable';

    private const VERIFICATION_INTERVAL = 60;

    private const TOKEN_EXPIRY_BUFFER = 30;

    public function verify(Request $request): string
    {
        $session = $request->session();

        $now = (int) now()->timestamp;

        $authId = $session->get('elsewhere_auth_id');

        $accessToken = $this->decryptToken(
            $session->get('elsewhere_oauth_access_token')
        );

        $refreshToken = $this->decryptToken(
            $session->get('elsewhere_oauth_refresh_token')
        );

        $expiresAt = $session->get(
            'elsewhere_oauth_expires_at'
        );

        $verifiedAt = $session->get(
            'elsewhere_membership_verified_at'
        );

        if (
            ! is_string($authId) ||
            ! Str::isUuid($authId) ||
            $accessToken === null ||
            $refreshToken === null ||
            ! is_int($expiresAt) ||
            $expiresAt <= 0
        ) {
            return self::UNAUTHENTICATED;
        }

        $baseUrl = $this->baseUrl();

        if ($baseUrl === null) {
            return self::UNAVAILABLE;
        }

        /*
         * Refresh an access token before it expires.
         */
        if (
            $expiresAt <= $now + self::TOKEN_EXPIRY_BUFFER
        ) {
            $refreshResult = $this->refreshToken(
                $request,
                $baseUrl,
                $refreshToken
            );

            if ($refreshResult !== self::ACTIVE) {
                return $refreshResult;
            }

            $accessToken = $this->decryptToken(
                $session->get('elsewhere_oauth_access_token')
            );

            if ($accessToken === null) {
                return self::UNAUTHENTICATED;
            }

            /*
             * Refreshing credentials does not itself
             * verify application membership.
             */
            $verifiedAt = null;
        }

        /*
         * Avoid calling Elsewhere on every request.
         *
         * Only successful verification is cached.
         */
        if (
            is_int($verifiedAt) &&
            $verifiedAt <= $now &&
            $now - $verifiedAt < self::VERIFICATION_INTERVAL
        ) {
            return self::ACTIVE;
        }

        return $this->checkMembership(
            $request,
            $baseUrl,
            $authId,
            $accessToken,
            true
        );
    }

    private function checkMembership(
        Request $request,
        string $baseUrl,
        string $authId,
        string $accessToken,
        bool $allowRefresh
    ): string {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get($baseUrl.'/api/auth/me');
        } catch (ConnectionException) {
            return self::UNAVAILABLE;
        }

        /*
         * A revoked or expired access token may
         * be recoverable through the refresh grant.
         */
        if ($response->status() === 401 && $allowRefresh) {
            $refreshToken = $this->decryptToken(
                $request->session()->get(
                    'elsewhere_oauth_refresh_token'
                )
            );

            if ($refreshToken === null) {
                return self::UNAUTHENTICATED;
            }

            $refreshResult = $this->refreshToken(
                $request,
                $baseUrl,
                $refreshToken
            );

            if ($refreshResult !== self::ACTIVE) {
                return $refreshResult;
            }

            $newAccessToken = $this->decryptToken(
                $request->session()->get(
                    'elsewhere_oauth_access_token'
                )
            );

            if ($newAccessToken === null) {
                return self::UNAUTHENTICATED;
            }

            return $this->checkMembership(
                $request,
                $baseUrl,
                $authId,
                $newAccessToken,
                false
            );
        }

        if ($response->status() === 401) {
            return self::UNAUTHENTICATED;
        }

        /*
         * Elsewhere explicitly rejected application
         * access for these OAuth credentials.
         */
        if ($response->status() === 403) {
            return self::DENIED;
        }

        /*
         * Network-independent failures, including
         * server errors, cannot establish membership.
         */
        if (! $response->successful()) {
            return self::UNAVAILABLE;
        }

        $verifiedAuthId = $response->json('auth_id');

        /*
         * A token must never authenticate a different
         * account from the one stored in the session.
         */
        if (
            ! is_string($verifiedAuthId) ||
            ! Str::isUuid($verifiedAuthId) ||
            ! hash_equals($authId, $verifiedAuthId)
        ) {
            return self::DENIED;
        }

        $request->session()->put(
            'elsewhere_membership_verified_at',
            now()->timestamp
        );

        return self::ACTIVE;
    }

    private function refreshToken(
        Request $request,
        string $baseUrl,
        string $refreshToken
    ): string {
        $clientId = config('elsewhere.client_id');

        if (
            ! is_string($clientId) ||
            ! Str::isUuid($clientId)
        ) {
            return self::UNAVAILABLE;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->post($baseUrl.'/oauth/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                ]);
        } catch (ConnectionException) {
            return self::UNAVAILABLE;
        }

        /*
         * Invalid refresh credentials require
         * a new interactive OAuth login.
         */
        if (in_array($response->status(), [400, 401], true)) {
            return self::UNAUTHENTICATED;
        }

        if (! $response->successful()) {
            return self::UNAVAILABLE;
        }

        $accessToken = $response->json('access_token');
        $newRefreshToken = $response->json('refresh_token');
        $expiresIn = $response->json('expires_in');

        if (
            ! is_string($accessToken) ||
            $accessToken === '' ||
            ! is_string($newRefreshToken) ||
            $newRefreshToken === '' ||
            ! is_int($expiresIn) ||
            $expiresIn <= 0
        ) {
            return self::UNAVAILABLE;
        }

        /*
         * Passport may rotate the refresh token.
         * Always save the replacement credentials.
         */
        $request->session()->put([
            'elsewhere_oauth_access_token' => Crypt::encryptString(
                $accessToken
            ),
            'elsewhere_oauth_refresh_token' => Crypt::encryptString(
                $newRefreshToken
            ),
            'elsewhere_oauth_expires_at' => now()
                ->addSeconds($expiresIn)
                ->timestamp,
        ]);

        $request->session()->forget(
            'elsewhere_membership_verified_at'
        );

        return self::ACTIVE;
    }

    private function decryptToken(mixed $encrypted): ?string
    {
        if (
            ! is_string($encrypted) ||
            $encrypted === ''
        ) {
            return null;
        }

        try {
            $token = Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }

        return $token !== '' ? $token : null;
    }

    private function baseUrl(): ?string
    {
        $url = config('elsewhere.url');

        if (
            ! is_string($url) ||
            ! filter_var($url, FILTER_VALIDATE_URL) ||
            ! in_array(
                parse_url($url, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            ) ||
            parse_url($url, PHP_URL_USER) !== null ||
            parse_url($url, PHP_URL_PASS) !== null
        ) {
            return null;
        }

        return rtrim($url, '/');
    }
}
