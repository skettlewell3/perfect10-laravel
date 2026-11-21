<?php

namespace Tests\Feature;

use App\Services\Perfect10\ProfileProvisioningService;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class ElsewhereProfileProvisioningControllerTest extends TestCase
{
    private const URL = '/api/integrations/elsewhere/profiles';

    private const AUTH_ID =
        '11111111-1111-4111-8111-111111111111';

    private const PROFILE_ID =
        '22222222-2222-4222-8222-222222222222';

    private const SIGNING_KEY =
        '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('elsewhere_provisioning.enabled', true);

        config()->set(
            'elsewhere_provisioning.signing_key',
            self::SIGNING_KEY
        );

        config()->set(
            'elsewhere_provisioning.nonce_cache_store',
            'array'
        );

        config()->set(
            'elsewhere_provisioning.timestamp_tolerance',
            300
        );
    }

    private function sendSignedRequest(array $payload): TestResponse
    {
        $body = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));

        $message = $timestamp."\n"
            .$nonce."\n"
            .$body;

        $signature = hash_hmac(
            'sha256',
            $message,
            hex2bin(self::SIGNING_KEY)
        );

        return $this->call(
            'POST',
            self::URL,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_ELSEWHERE_TIMESTAMP' => $timestamp,
                'HTTP_X_ELSEWHERE_NONCE' => $nonce,
                'HTTP_X_ELSEWHERE_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    public function test_valid_request_provisions_profile(): void
    {
        $service = Mockery::mock(
            ProfileProvisioningService::class
        );

        $service->shouldReceive('provision')
            ->once()
            ->with(self::AUTH_ID, 'test_player')
            ->andReturn(self::PROFILE_ID);

        $this->app->instance(
            ProfileProvisioningService::class,
            $service
        );

        $this->sendSignedRequest([
            'auth_id' => self::AUTH_ID,
            'username' => 'test_player',
        ])
            ->assertOk()
            ->assertJson([
                'profile_id' => self::PROFILE_ID,
                'provisioned' => true,
            ]);
    }

    public function test_invalid_uuid_is_rejected(): void
    {
        $service = Mockery::mock(
            ProfileProvisioningService::class
        );

        $service->shouldNotReceive('provision');

        $this->app->instance(
            ProfileProvisioningService::class,
            $service
        );

        $this->sendSignedRequest([
            'auth_id' => 'invalid-uuid',
            'username' => 'test_player',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('auth_id');
    }

    public function test_username_over_16_characters_is_rejected(): void
    {
        $service = Mockery::mock(
            ProfileProvisioningService::class
        );

        $service->shouldNotReceive('provision');

        $this->app->instance(
            ProfileProvisioningService::class,
            $service
        );

        $this->sendSignedRequest([
            'auth_id' => self::AUTH_ID,
            'username' => str_repeat('a', 17),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_disabled_provisioning_does_not_call_service(): void
    {
        config()->set(
            'elsewhere_provisioning.enabled',
            false
        );

        $service = Mockery::mock(
            ProfileProvisioningService::class
        );

        $service->shouldNotReceive('provision');

        $this->app->instance(
            ProfileProvisioningService::class,
            $service
        );

        $this->sendSignedRequest([
            'auth_id' => self::AUTH_ID,
            'username' => 'test_player',
        ])
            ->assertStatus(503);
    }

    public function test_unsigned_request_is_rejected(): void
    {
        $service = Mockery::mock(
            ProfileProvisioningService::class
        );

        $service->shouldNotReceive('provision');

        $this->app->instance(
            ProfileProvisioningService::class,
            $service
        );

        $this->postJson(self::URL, [
            'auth_id' => self::AUTH_ID,
            'username' => 'test_player',
        ])
            ->assertStatus(401);
    }
}
