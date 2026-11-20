<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\TestCase;

class ElsewhereLogoutTest extends TestCase
{
    private const AUTH_ID = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'array');

        config()->set(
            'elsewhere.url',
            'http://127.0.0.1:8000'
        );

        $this->withoutMiddleware(
            HandleInertiaRequests::class
        );
    }

    public function test_logout_clears_session_and_redirects_to_elsewhere(): void
    {
        $response = $this
            ->withSession([
                'elsewhere_auth_id' => self::AUTH_ID,
                'perfect10.last_flavour' => 'WC',
            ])
            ->withHeader('X-Inertia', 'true')
            ->post(route('elsewhere.auth.logout'));

        $response->assertStatus(409);

        $response->assertHeader(
            'X-Inertia-Location',
            'http://127.0.0.1:8000/auth/logout/perfect10'
        );

        $response->assertSessionMissing(
            'elsewhere_auth_id'
        );

        $response->assertSessionMissing(
            'perfect10.last_flavour'
        );
    }

    public function test_guest_cannot_access_logout_endpoint(): void
    {
        $response = $this->post(
            route('elsewhere.auth.logout')
        );

        $response->assertRedirect(
            route('elsewhere.auth.redirect')
        );
    }
}
