<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyElsewhereProvisioningSignature
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! config('elsewhere_provisioning.enabled', false)) {
            abort(503, 'Profile provisioning is unavailable.');
        }

        $configuredKey = config(
            'elsewhere_provisioning.signing_key'
        );

        if (
            ! is_string($configuredKey) ||
            ! preg_match('/\A[a-fA-F0-9]{64}\z/', $configuredKey)
        ) {
            abort(503, 'Provisioning authentication unavailable.');
        }

        $key = hex2bin($configuredKey);

        if ($key === false) {
            abort(503, 'Provisioning authentication unavailable.');
        }

        $timestamp = $request->header(
            'X-Elsewhere-Timestamp'
        );

        $nonce = $request->header(
            'X-Elsewhere-Nonce'
        );

        $signature = $request->header(
            'X-Elsewhere-Signature'
        );

        if (
            ! is_string($timestamp) ||
            ! preg_match('/\A[0-9]{10}\z/', $timestamp) ||
            ! is_string($nonce) ||
            ! preg_match('/\A[a-fA-F0-9]{32}\z/', $nonce) ||
            ! is_string($signature) ||
            ! preg_match('/\A[a-fA-F0-9]{64}\z/', $signature)
        ) {
            abort(401, 'Invalid provisioning signature.');
        }

        $tolerance = (int) config(
            'elsewhere_provisioning.timestamp_tolerance',
            300
        );

        if (
            $tolerance < 1 ||
            abs(time() - (int) $timestamp) > $tolerance
        ) {
            abort(401, 'Expired provisioning signature.');
        }

        /*
         * Sign the raw request body, not parsed JSON.
         * Both applications must use the identical message format.
         */
        $message = $timestamp."\n"
            .$nonce."\n"
            .$request->getContent();

        $expectedSignature = hash_hmac(
            'sha256',
            $message,
            $key
        );

        if (! hash_equals(
            $expectedSignature,
            strtolower($signature)
        )) {
            abort(401, 'Invalid provisioning signature.');
        }

        /*
         * A valid signature can only be used once.
         */
        $cacheStore = config(
            'elsewhere_provisioning.nonce_cache_store',
            'file'
        );

        if (! is_string($cacheStore) || $cacheStore === '') {
            abort(503, 'Replay protection unavailable.');
        }

        $cacheKey = 'elsewhere:provisioning:nonce:'
            .strtolower($nonce);

        try {
            $stored = Cache::store($cacheStore)->add(
                $cacheKey,
                true,
                ($tolerance * 2) + 60
            );
        } catch (\Throwable $exception) {
            report($exception);

            abort(503, 'Provisioning replay protection unavailable.');
        }

        if (! $stored) {
            abort(401, 'Provisioning request already used.');
        }

        return $next($request);
    }
}
