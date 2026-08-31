<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_the_redirect_route_points_at_google(): void
    {
        $this->get('/auth/google/redirect')
            ->assertRedirectContains('accounts.google.com/o/oauth2/auth');
    }

    /**
     * The GitHub scopes that used to be requested here made Google answer with
     * invalid_scope, so pin the scope list the driver actually sends.
     */
    public function test_only_the_google_scopes_are_requested(): void
    {
        $location = $this->get('/auth/google/redirect')->headers->get('Location');

        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertEqualsCanonicalizing(
            ['openid', 'profile', 'email'],
            explode(' ', $query['scope'])
        );
    }

    public function test_a_new_google_user_is_created_and_asked_to_enroll(): void
    {
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/two-factor');

        $user = User::firstWhere('google_id', '117001');

        $this->assertNotNull($user);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_users_cannot_use_the_app_before_enrolling_when_enforcement_is_on(): void
    {
        config(['mfa.enforce_for_oauth' => true]);
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback');

        $this->get('/dashboard')->assertRedirect('/two-factor');
    }

    public function test_enforcement_can_be_switched_off(): void
    {
        config(['mfa.enforce_for_oauth' => false]);
        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_an_existing_email_account_is_linked_instead_of_duplicated(): void
    {
        $existing = User::factory()->create(['email' => 'ada@example.com']);

        $this->fakeGoogleUser();
        $this->get('/auth/google/callback');

        $this->assertSame(1, User::count());
        $this->assertSame('117001', $existing->fresh()->google_id);
    }

    public function test_an_existing_github_account_with_the_same_email_is_linked(): void
    {
        $existing = User::factory()->github('583231')->create(['email' => 'ada@example.com']);

        $this->fakeGoogleUser();
        $this->get('/auth/google/callback');

        $this->assertSame(1, User::count());
        $this->assertSame('583231', $existing->fresh()->github_id);
        $this->assertSame('117001', $existing->fresh()->google_id);
    }

    public function test_a_google_user_with_two_factor_still_has_to_pass_the_challenge(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        User::factory()->google('117001')->withTwoFactor($secret)->create([
            'email' => 'ada@example.com',
        ]);

        $this->fakeGoogleUser();

        $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_a_cancelled_authorisation_returns_to_the_login_screen(): void
    {
        $this->get('/auth/google/callback?error=access_denied')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_failing_token_exchange_returns_to_the_login_screen(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('boom'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_an_account_without_an_email_is_rejected(): void
    {
        $this->fakeGoogleUser(['email' => null]);

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_both_provider_buttons_are_offered(): void
    {
        foreach (['/login', '/register'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee(route('github.redirect'), false)
                ->assertSee(route('google.redirect'), false);
        }
    }

    private function fakeGoogleUser(array $overrides = []): void
    {
        $googleUser = (new SocialiteUser)->map(array_merge([
            'id' => '117001',
            'nickname' => null,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'avatar' => 'https://lh3.googleusercontent.com/a/ada',
        ], $overrides));

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
