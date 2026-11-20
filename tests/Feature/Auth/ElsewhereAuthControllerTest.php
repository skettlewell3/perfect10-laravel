<?php

namespace Tests\Feature\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ElsewhereAuthControllerTest extends TestCase
{
    private const AUTH_ID = '11111111-1111-4111-8111-111111111111';

    private const CLIENT_ID = '01a1dab6-3f2c-71e1-9bc4-36e5dd95e67d';

    private const ELSEWHERE_URL = 'http://127.0.0.1:8000';

    private const CALLBACK_URL = 'http://localhost:8001/auth/elsewhere/callback';

    private const STATE = 'test-oauth-state-value';

    private const VERIFIER = 'test-pkce-code-verifier-value';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped(
                'These isolated tests require SQLite.'
            );
        }

        config()->set('session.driver', 'array');

        config()->set('elsewhere.url', self::ELSEWHERE_URL);
        config()->set('elsewhere.client_id', self::CLIENT_ID);
        config()->set('elsewhere.redirect_uri', self::CALLBACK_URL);

        Http::preventStrayRequests();

        $this->createTestTables();
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('user_profiles');
        }

        parent::tearDown();
    }

    private function createTestTables(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->uuid('auth_id')->primary();
            $table->uuid('profile_id');
            $table->string('username');
            $table->timestamps();
        });
    }

    private function createUserProfile(): void
    {
        DB::table('user_profiles')->insert([
            'auth_id' => self::AUTH_ID,
            'profile_id' => '22222222-2222-4222-8222-222222222222',
            'username' => 'test_player',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function withOAuthSession(): static
    {
        return $this->withSession([
            'elsewhere_oauth_state' => self::STATE,
            'elsewhere_oauth_verifier' => self::VERIFIER,
        ]);
    }

    private function callbackUrl(
        array $query = []
    ): string {
        return route('elsewhere.auth.callback').'?'.http_build_query(
            array_merge([
                'state' => self::STATE,
                'code' => 'test-authorization-code',
            ], $query)
        );
    }

    private function fakeSuccessfulExchange(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),

            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => self::AUTH_ID,
            ], 200),
        ]);
    }

    public function test_redirect_generates_pkce_parameters(): void
    {
        $response = $this->get(
            route('elsewhere.auth.redirect')
        );

        $response->assertRedirect();

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);

        $queryString = parse_url(
            $location,
            PHP_URL_QUERY
        );

        $this->assertIsString($queryString);

        parse_str($queryString, $parameters);

        $this->assertStringStartsWith(
            self::ELSEWHERE_URL.'/oauth/authorize?',
            $location
        );

        $this->assertSame(
            self::CLIENT_ID,
            $parameters['client_id']
        );

        $this->assertSame(
            self::CALLBACK_URL,
            $parameters['redirect_uri']
        );

        $this->assertSame(
            'code',
            $parameters['response_type']
        );

        $this->assertSame(
            'identity:read',
            $parameters['scope']
        );

        $this->assertSame(
            'S256',
            $parameters['code_challenge_method']
        );

        $state = session('elsewhere_oauth_state');
        $verifier = session('elsewhere_oauth_verifier');

        $this->assertIsString($state);
        $this->assertIsString($verifier);

        $this->assertNotEmpty($state);
        $this->assertNotEmpty($verifier);

        $this->assertSame(
            $state,
            $parameters['state']
        );

        $expectedChallenge = rtrim(
            strtr(
                base64_encode(
                    hash('sha256', $verifier, true)
                ),
                '+/',
                '-_'
            ),
            '='
        );

        $this->assertSame(
            $expectedChallenge,
            $parameters['code_challenge']
        );

        Http::assertNothingSent();
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl([
                'state' => 'incorrect-state',
            ]));

        $response->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_callback_rejects_missing_state(): void
    {
        $response = $this->get(
            $this->callbackUrl()
        );

        $response->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_callback_rejects_missing_verifier(): void
    {
        $response = $this
            ->withSession([
                'elsewhere_oauth_state' => self::STATE,
            ])
            ->get($this->callbackUrl());

        $response->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_callback_rejects_denied_authorization(): void
    {
        $response = $this
            ->withOAuthSession()
            ->get(route('elsewhere.auth.callback').'?'.http_build_query([
                'state' => self::STATE,
                'error' => 'access_denied',
            ]));

        $response->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_callback_rejects_missing_code(): void
    {
        $response = $this
            ->withOAuthSession()
            ->get(route('elsewhere.auth.callback').'?'.http_build_query([
                'state' => self::STATE,
            ]));

        $response->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_callback_rejects_failed_token_exchange(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl());

        $response->assertStatus(502);

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );

        Http::assertSentCount(1);
    }

    public function test_callback_rejects_missing_access_token(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl());

        $response->assertStatus(502);

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );

        Http::assertSentCount(1);
    }

    public function test_callback_rejects_identity_endpoint_denial(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),

            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'message' => 'Application membership required.',
            ], 403),
        ]);

        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl());

        $response->assertForbidden();

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );

        Http::assertSentCount(2);
    }

    public function test_callback_rejects_invalid_identity(): void
    {
        Http::fake([
            self::ELSEWHERE_URL.'/oauth/token' => Http::response([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),

            self::ELSEWHERE_URL.'/api/auth/me' => Http::response([
                'auth_id' => 'not-a-uuid',
            ], 200),
        ]);

        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl());

        $response->assertForbidden();

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );
    }

    public function test_callback_rejects_missing_perfect10_profile(): void
    {
        $this->fakeSuccessfulExchange();

        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl());

        $response->assertForbidden();

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );
    }

    public function test_successful_callback_establishes_session(): void
    {
        $this->createUserProfile();

        $this->fakeSuccessfulExchange();

        $response = $this
            ->withOAuthSession()
            ->get($this->callbackUrl());

        $response->assertRedirect('/');

        $response->assertSessionHas(
            'elsewhere_auth_id',
            self::AUTH_ID
        );

        $response->assertSessionMissing(
            'elsewhere_oauth_state'
        );

        $response->assertSessionMissing(
            'elsewhere_oauth_verifier'
        );

        Http::assertSentCount(2);

        Http::assertSent(
            fn (ClientRequest $request): bool => $request->url() === self::ELSEWHERE_URL.'/oauth/token'
                && $request->method() === 'POST'
                && $request['grant_type'] === 'authorization_code'
                && $request['client_id'] === self::CLIENT_ID
                && $request['redirect_uri'] === self::CALLBACK_URL
                && $request['code'] === 'test-authorization-code'
                && $request['code_verifier'] === self::VERIFIER
        );

        Http::assertSent(
            fn (ClientRequest $request): bool => $request->url() === self::ELSEWHERE_URL.'/api/auth/me'
                && $request->method() === 'GET'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer test-access-token'
                )
        );
    }

    public function test_starting_login_clears_previous_identity(): void
    {
        $response = $this
            ->withSession([
                'elsewhere_auth_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            ])
            ->get(route('elsewhere.auth.redirect'));

        $response->assertRedirect();

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );

        $response->assertSessionHas(
            'elsewhere_oauth_state'
        );

        $response->assertSessionHas(
            'elsewhere_oauth_verifier'
        );
    }
}
