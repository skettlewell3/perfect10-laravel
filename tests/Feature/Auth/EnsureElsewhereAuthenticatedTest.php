<?php

namespace Tests\Feature\Auth;

use App\Contracts\Auth\CurrentAuthIdentity;
use App\Http\Middleware\EnsureElsewhereAuthenticated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Services\Auth\ElsewhereMembershipService;
use App\Services\Auth\SessionCurrentAuthIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class EnsureElsewhereAuthenticatedTest extends TestCase
{
    private const AUTH_ID = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');

        $this->withoutMiddleware(
            HandleInertiaRequests::class
        );

        $this->app->bind(
            CurrentAuthIdentity::class,
            SessionCurrentAuthIdentity::class
        );

        Route::middleware([
            'web',
            EnsureElsewhereAuthenticated::class,
        ])->group(function (): void {
            Route::get('/_test/elsewhere-protected', function () {
                return response('Protected Perfect10 page');
            });

            Route::get('/_test/elsewhere-protected-json', function () {
                return response()->json([
                    'message' => 'Authenticated',
                ]);
            });
        });
    }

    private function mockMembership(
        string $result
    ): void {
        $membership = Mockery::mock(
            ElsewhereMembershipService::class
        );

        $membership->shouldReceive('verify')
            ->once()
            ->with(Mockery::type(Request::class))
            ->andReturn($result);

        $this->app->instance(
            ElsewhereMembershipService::class,
            $membership
        );
    }

    public function test_guest_is_redirected_to_elsewhere_login(): void
    {
        $response = $this->get(
            '/_test/elsewhere-protected'
        );

        $response->assertRedirect(
            route('elsewhere.auth.redirect')
        );
    }

    public function test_guest_json_request_returns_unauthorized(): void
    {
        $response = $this->getJson(
            '/_test/elsewhere-protected-json'
        );

        $response->assertUnauthorized();

        $response->assertJson([
            'message' => 'Authentication is required.',
        ]);
    }

    public function test_valid_session_identity_can_access_page(): void
    {
        $this->mockMembership(
            ElsewhereMembershipService::ACTIVE
        );

        $response = $this
            ->withSession([
                'elsewhere_auth_id' => self::AUTH_ID,
            ])
            ->get('/_test/elsewhere-protected');

        $response->assertOk();

        $response->assertSee(
            'Protected Perfect10 page'
        );
    }

    public function test_valid_session_identity_can_access_json_endpoint(): void
    {
        $this->mockMembership(
            ElsewhereMembershipService::ACTIVE
        );

        $response = $this
            ->withSession([
                'elsewhere_auth_id' => self::AUTH_ID,
            ])
            ->getJson('/_test/elsewhere-protected-json');

        $response->assertOk();

        $response->assertExactJson([
            'message' => 'Authenticated',
        ]);
    }

    public function test_invalid_session_identity_is_rejected(): void
    {
        $response = $this
            ->withSession([
                'elsewhere_auth_id' => 'invalid-auth-id',
            ])
            ->get('/_test/elsewhere-protected');

        $response->assertRedirect(
            route('elsewhere.auth.redirect')
        );
    }

    public function test_empty_session_identity_is_rejected(): void
    {
        $response = $this
            ->withSession([
                'elsewhere_auth_id' => '',
            ])
            ->get('/_test/elsewhere-protected');

        $response->assertRedirect(
            route('elsewhere.auth.redirect')
        );
    }

    public function test_non_string_session_identity_is_rejected(): void
    {
        $response = $this
            ->withSession([
                'elsewhere_auth_id' => 12345,
            ])
            ->getJson('/_test/elsewhere-protected-json');

        $response->assertUnauthorized();
    }

    public function test_development_auth_id_does_not_bypass_authentication(): void
    {
        config()->set(
            'perfect10.dev_auth_id',
            self::AUTH_ID
        );

        $response = $this->get(
            '/_test/elsewhere-protected'
        );

        $response->assertRedirect(
            route('elsewhere.auth.redirect')
        );
    }

    public function test_suspended_membership_is_denied_and_session_cleared(): void
    {
        $this->mockMembership(
            ElsewhereMembershipService::DENIED
        );

        $response = $this
            ->withSession([
                'elsewhere_auth_id' => self::AUTH_ID,
            ])
            ->get('/_test/elsewhere-protected');

        $response->assertForbidden();

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );
    }

    public function test_unavailable_elsewhere_blocks_access_without_clearing_session(): void
    {
        $this->mockMembership(
            ElsewhereMembershipService::UNAVAILABLE
        );

        $response = $this
            ->withSession([
                'elsewhere_auth_id' => self::AUTH_ID,
            ])
            ->get('/_test/elsewhere-protected');

        $response->assertStatus(503);

        $response->assertSessionHas(
            'elsewhere_auth_id',
            self::AUTH_ID
        );
    }

    public function test_invalid_credentials_require_new_authentication(): void
    {
        $this->mockMembership(
            ElsewhereMembershipService::UNAUTHENTICATED
        );

        $response = $this
            ->withSession([
                'elsewhere_auth_id' => self::AUTH_ID,
            ])
            ->get('/_test/elsewhere-protected');

        $response->assertRedirect(
            route('elsewhere.auth.redirect')
        );

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );
    }

    public function test_guest_does_not_trigger_membership_verification(): void
    {
        $membership = Mockery::mock(
            ElsewhereMembershipService::class
        );

        $membership->shouldNotReceive('verify');

        $this->app->instance(
            ElsewhereMembershipService::class,
            $membership
        );

        $this->get('/_test/elsewhere-protected')
            ->assertRedirect(
                route('elsewhere.auth.redirect')
            );
    }
}
