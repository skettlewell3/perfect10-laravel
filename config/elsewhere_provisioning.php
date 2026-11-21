<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Elsewhere Profile Provisioning
    |--------------------------------------------------------------------------
    |
    | Disabled until both applications and the database migrations
    | have been tested and are ready for activation.
    |
    */

    'enabled' => env(
        'ELSEWHERE_PROVISIONING_ENABLED',
        false
    ),

    /*
     * Shared signing key represented as 64 hexadecimal characters.
     * The same key must be configured securely in Elsewhere.
     */
    'signing_key' => env(
        'ELSEWHERE_PROVISIONING_SIGNING_KEY'
    ),

    /*
     * Maximum permitted clock difference in seconds.
     */
    'timestamp_tolerance' => 300,

    /*
     * Replay protection cache.
     *
     * A shared cache is required for multi-instance deployment.
     * The array store is suitable for isolated tests only.
     */
    'nonce_cache_store' => env(
        'ELSEWHERE_PROVISIONING_NONCE_CACHE_STORE',
        'elsewhere_provisioning'
    ),
];
