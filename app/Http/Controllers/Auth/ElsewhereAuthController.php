<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Perfect10\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ElsewhereAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('elsewhere_auth_id');
        $request->session()->regenerate();

        $baseUrl = $this->baseUrl();
        $clientId = $this->clientId();
        $redirectUri = $this->redirectUri();

        $state = Str::random(40);
        $verifier = Str::random(96);

        $challenge = rtrim(
            strtr(
                base64_encode(
                    hash('sha256', $verifier, true)
                ),
                '+/',
                '-_'
            ),
            '='
        );

        $request->session()->put([
            'elsewhere_oauth_state' => $state,
            'elsewhere_oauth_verifier' => $verifier,
        ]);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identity:read',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(
            $baseUrl.'/oauth/authorize?'.$query
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()
            ->pull('elsewhere_oauth_state');

        $verifier = $request->session()
            ->pull('elsewhere_oauth_verifier');

        $state = $request->query('state');
        $code = $request->query('code');

        abort_unless(
            is_string($expectedState) &&
            is_string($state) &&
            hash_equals($expectedState, $state),
            403,
            'Invalid authentication state.'
        );

        abort_unless(
            is_string($verifier) &&
            $verifier !== '',
            403,
            'Missing authentication verifier.'
        );

        if ($request->has('error')) {
            abort(403, 'Authentication was not approved.');
        }

        abort_unless(
            is_string($code) &&
            $code !== '',
            403,
            'Missing authorization code.'
        );

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->post($this->baseUrl().'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId(),
                'redirect_uri' => $this->redirectUri(),
                'code' => $code,
                'code_verifier' => $verifier,
            ]);

        abort_unless(
            $tokenResponse->successful(),
            502,
            'Elsewhere token exchange failed.'
        );

        $accessToken = $tokenResponse->json('access_token');

        abort_unless(
            is_string($accessToken) &&
            $accessToken !== '',
            502,
            'Elsewhere did not return an access token.'
        );

        $identityResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->get($this->baseUrl().'/api/auth/me');

        abort_unless(
            $identityResponse->successful(),
            403,
            'Elsewhere did not authorize Perfect10 access.'
        );

        $authId = $identityResponse->json('auth_id');

        abort_unless(
            is_string($authId) &&
            Str::isUuid($authId),
            403,
            'Invalid Elsewhere identity.'
        );

        $hasProfile = UserProfile::query()
            ->where('auth_id', $authId)
            ->exists();

        abort_unless(
            $hasProfile,
            403,
            'An existing Perfect10 profile is required.'
        );

        $request->session()->put(
            'elsewhere_auth_id',
            $authId
        );

        $request->session()->regenerate();

        return redirect('/');
    }

    private function baseUrl(): string
    {
        $url = config('elsewhere.url');

        abort_unless(
            is_string($url) &&
            filter_var($url, FILTER_VALIDATE_URL) &&
            in_array(
                parse_url($url, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            ),
            500,
            'Elsewhere URL is not configured.'
        );

        return rtrim($url, '/');
    }

    private function clientId(): string
    {
        $clientId = config('elsewhere.client_id');

        abort_unless(
            is_string($clientId) &&
            Str::isUuid($clientId),
            500,
            'Elsewhere OAuth client is not configured.'
        );

        return $clientId;
    }

    private function redirectUri(): string
    {
        $uri = config('elsewhere.redirect_uri');

        abort_unless(
            is_string($uri) &&
            filter_var($uri, FILTER_VALIDATE_URL),
            500,
            'Elsewhere OAuth callback is not configured.'
        );

        return $uri;
    }
}
