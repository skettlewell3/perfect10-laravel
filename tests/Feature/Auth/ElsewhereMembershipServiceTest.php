<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\ElsewhereMembershipService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ElsewhereMembershipServiceTest extends TestCase
{
    private const AUTH_ID = '11111111-1111-4111-8111-111111111111';

    private const ELSEWHERE_URL = 'http://127.0.0.1:8000';

    private const CLIENT_ID = '01a1dab6-3f2c-71e1-9bc4-36e5dd95e67d';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');
        config()->set('elsewhere.url', self::ELSEWHERE_URL);
        config()->set('elsewhere.client_id', self::CLIENT_ID);

        Http::preventStrayRequests();
    }

    private function authenticatedRequest(
        array $overrides = []
    ): Request {
        $session = app('session')->driver('array');

        $session->start();
        $session->flush();

        $session->put(array_merge([
            'elsewhere_auth_id' => self::AUTH_ID,
            'elsewhere_oauth_access_token' => Crypt::encryptString(
                'original-access-token'
            ),
            'elsewhere_oauth_refresh_token' => Crypt::encryptString(
                'original-refresh-token'
            ),
            'elsewhere_oauth_expires_at' => now()
                ->addHour()
                ->timestamp,
            'elsewhere_membership_verified_at' => null,
        ], $overrides));

        $request = Request::create('/');

        $request->setLaravelSession($session);

        return $request;
    }

    private function verify(Request $request): string
    {
        return app(ElsewhereMembershipService::class)
            ->verify($request);
    }

    public function test_active_membership_is_verified(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => self::AUTH_ID,
            ], 200),
        ]);

        $request = $this->authenticatedRequest();

        $result = $this->verify($request);

        $this->assertSame(
            ElsewhereMembershipService::ACTIVE,
            $result
        );

        $this->assertSame(
            now()->timestamp,
            $request->session()->get(
                'elsewhere_membership_verified_at'
            )
        );

        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            return $request->url() ===
                self::ELSEWHERE_URL.'/api/auth/me'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer original-access-token'
                );
        });
    }

    public function test_recent_verification_does_not_call_elsewhere(): void
    {
        Http::fake();

        $request = $this->authenticatedRequest([
            'elsewhere_membership_verified_at' => now()
                ->subSeconds(30)
                ->timestamp,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::ACTIVE,
            $this->verify($request)
        );

        Http::assertNothingSent();
    }

    public function test_expired_verification_checks_elsewhere_again(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => self::AUTH_ID,
            ], 200),
        ]);

        $request = $this->authenticatedRequest([
            'elsewhere_membership_verified_at' => now()
                ->subSeconds(61)
                ->timestamp,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::ACTIVE,
            $this->verify($request)
        );

        Http::assertSentCount(1);
    }

    public function test_suspended_membership_is_denied(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'message' => 'Application membership required.',
            ], 403),
        ]);

        $request = $this->authenticatedRequest();

        $this->assertSame(
            ElsewhereMembershipService::DENIED,
            $this->verify($request)
        );

        $this->assertNull(
            $request->session()->get(
                'elsewhere_membership_verified_at'
            )
        );
    }

    public function test_missing_credentials_require_authentication(): void
    {
        Http::fake();

        $request = $this->authenticatedRequest([
            'elsewhere_oauth_access_token' => null,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::UNAUTHENTICATED,
            $this->verify($request)
        );

        Http::assertNothingSent();
    }

    public function test_corrupted_encrypted_token_requires_authentication(): void
    {
        Http::fake();

        $request = $this->authenticatedRequest([
            'elsewhere_oauth_access_token' => 'invalid-ciphertext',
        ]);

        $this->assertSame(
            ElsewhereMembershipService::UNAUTHENTICATED,
            $this->verify($request)
        );

        Http::assertNothingSent();
    }

    public function test_different_identity_is_denied(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => '22222222-2222-4222-8222-222222222222',
            ], 200),
        ]);

        $request = $this->authenticatedRequest();

        $this->assertSame(
            ElsewhereMembershipService::DENIED,
            $this->verify($request)
        );

        $this->assertNull(
            $request->session()->get(
                'elsewhere_membership_verified_at'
            )
        );
    }

    public function test_elsewhere_server_failure_blocks_verification(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'message' => 'Service unavailable.',
            ], 503),
        ]);

        $request = $this->authenticatedRequest();

        $this->assertSame(
            ElsewhereMembershipService::UNAVAILABLE,
            $this->verify($request)
        );

        Http::assertSentCount(1);
    }

    public function test_expired_token_is_refreshed_and_membership_verified(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'access_token' => 'replacement-access-token',
                'refresh_token' => 'replacement-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),

            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => self::AUTH_ID,
            ], 200),
        ]);

        $request = $this->authenticatedRequest([
            'elsewhere_oauth_expires_at' => now()
                ->subMinute()
                ->timestamp,
            'elsewhere_membership_verified_at' => now()->timestamp,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::ACTIVE,
            $this->verify($request)
        );

        $this->assertSame(
            'replacement-access-token',
            Crypt::decryptString(
                $request->session()->get(
                    'elsewhere_oauth_access_token'
                )
            )
        );

        $this->assertSame(
            'replacement-refresh-token',
            Crypt::decryptString(
                $request->session()->get(
                    'elsewhere_oauth_refresh_token'
                )
            )
        );

        $this->assertSame(
            now()->timestamp,
            $request->session()->get(
                'elsewhere_membership_verified_at'
            )
        );

        Http::assertSentCount(2);

        Http::assertSent(function ($request) {
            return $request->url() ===
                self::ELSEWHERE_URL.'/oauth/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] ===
                    'original-refresh-token'
                && $request['client_id'] === self::CLIENT_ID;
        });

        Http::assertSent(function ($request) {
            return $request->url() ===
                self::ELSEWHERE_URL.'/api/auth/me'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer replacement-access-token'
                );
        });
    }

    public function test_invalid_refresh_token_requires_authentication(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $request = $this->authenticatedRequest([
            'elsewhere_oauth_expires_at' => now()
                ->subMinute()
                ->timestamp,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::UNAUTHENTICATED,
            $this->verify($request)
        );

        Http::assertSentCount(1);
    }

    public function test_unauthorised_access_token_is_refreshed_once(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::sequence()
                ->push([
                    'message' => 'Unauthenticated.',
                ], 401)
                ->push([
                    'auth_id' => self::AUTH_ID,
                ], 200),

            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'access_token' => 'replacement-access-token',
                'refresh_token' => 'replacement-refresh-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $request = $this->authenticatedRequest();

        $this->assertSame(
            ElsewhereMembershipService::ACTIVE,
            $this->verify($request)
        );

        Http::assertSentCount(3);
    }

    public function test_network_failure_returns_unavailable(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => fn () => throw new ConnectionException(
                'Connection refused.'
            ),
        ]);

        $request = $this->authenticatedRequest();

        $this->assertSame(
            ElsewhereMembershipService::UNAVAILABLE,
            $this->verify($request)
        );
    }

    public function test_verification_at_exactly_sixty_seconds_is_refreshed(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => self::AUTH_ID,
            ], 200),
        ]);

        $request = $this->authenticatedRequest([
            'elsewhere_membership_verified_at' => now()
                ->subSeconds(60)
                ->timestamp,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::ACTIVE,
            $this->verify($request)
        );

        Http::assertSentCount(1);
    }

    public function test_invalid_refresh_response_returns_unavailable(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'access_token' => 'replacement-access-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $request = $this->authenticatedRequest([
            'elsewhere_oauth_expires_at' => now()
                ->subMinute()
                ->timestamp,
        ]);

        $this->assertSame(
            ElsewhereMembershipService::UNAVAILABLE,
            $this->verify($request)
        );

        $this->assertSame(
            'original-refresh-token',
            Crypt::decryptString(
                $request->session()->get(
                    'elsewhere_oauth_refresh_token'
                )
            )
        );
    }
}
