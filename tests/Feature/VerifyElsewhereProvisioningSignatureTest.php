<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyElsewhereProvisioningSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class VerifyElsewhereProvisioningSignatureTest extends TestCase
{
    private const URL = '/api/_test/elsewhere-provisioning';

    private const SIGNING_KEY =
        '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private const NONCE =
        'abcdef0123456789abcdef0123456789';

    private const BODY =
        '{"auth_id":"11111111-1111-4111-8111-111111111111","username":"test_player"}';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('elsewhere_provisioning.enabled', true);

        config()->set(
            'elsewhere_provisioning.signing_key',
            self::SIGNING_KEY
        );

        config()->set(
            'elsewhere_provisioning.timestamp_tolerance',
            300
        );

        config()->set(
            'elsewhere_provisioning.nonce_cache_store',
            'array'
        );

        /*
         * Register a test-only route protected by the real middleware.
         * No database tables or application profiles are required.
         */
        Route::post(self::URL, function () {
            return response()->json([
                'accepted' => true,
            ]);
        })->middleware(
            VerifyElsewhereProvisioningSignature::class
        );
    }

    private function signedHeaders(
        ?string $timestamp = null,
        string $nonce = self::NONCE,
        string $body = self::BODY
    ): array {
        $timestamp ??= (string) time();

        $message = $timestamp."\n"
            .$nonce."\n"
            .$body;

        $signature = hash_hmac(
            'sha256',
            $message,
            hex2bin(self::SIGNING_KEY)
        );

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ELSEWHERE_TIMESTAMP' => $timestamp,
            'HTTP_X_ELSEWHERE_NONCE' => $nonce,
            'HTTP_X_ELSEWHERE_SIGNATURE' => $signature,
        ];
    }

    private function sendSignedRequest(
        ?string $timestamp = null,
        string $nonce = self::NONCE,
        string $body = self::BODY
    ): TestResponse {
        return $this->call(
            'POST',
            self::URL,
            [],
            [],
            [],
            $this->signedHeaders(
                $timestamp,
                $nonce,
                $body
            ),
            $body
        );
    }

    public function test_disabled_provisioning_returns_503(): void
    {
        config()->set(
            'elsewhere_provisioning.enabled',
            false
        );

        $this->sendSignedRequest()
            ->assertStatus(503);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->call(
            'POST',
            self::URL,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            self::BODY
        )->assertStatus(401);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $headers = $this->signedHeaders();

        $headers['HTTP_X_ELSEWHERE_SIGNATURE'] =
            str_repeat('0', 64);

        $this->call(
            'POST',
            self::URL,
            [],
            [],
            [],
            $headers,
            self::BODY
        )->assertStatus(401);
    }

    public function test_valid_signed_request_is_accepted(): void
    {
        $this->sendSignedRequest()
            ->assertOk()
            ->assertJson([
                'accepted' => true,
            ]);
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $timestamp = (string) (time() - 600);

        $this->sendSignedRequest($timestamp)
            ->assertStatus(401);
    }

    public function test_modified_request_body_is_rejected(): void
    {
        $headers = $this->signedHeaders();

        $modifiedBody =
            '{"auth_id":"22222222-2222-4222-8222-222222222222","username":"test_player"}';

        $this->call(
            'POST',
            self::URL,
            [],
            [],
            [],
            $headers,
            $modifiedBody
        )->assertStatus(401);
    }

    public function test_reused_nonce_is_rejected(): void
    {
        $this->sendSignedRequest()
            ->assertOk();

        $this->sendSignedRequest()
            ->assertStatus(401);
    }

    public function test_cache_failure_returns_503(): void
    {
        Cache::shouldReceive('store')
            ->once()
            ->with('array')
            ->andThrow(
                new RuntimeException('Cache unavailable')
            );

        $this->sendSignedRequest()
            ->assertStatus(503);
    }
}
