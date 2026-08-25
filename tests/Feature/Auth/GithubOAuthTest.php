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

class GithubOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.client_id' => 'test-client-id',
            'services.github.client_secret' => 'test-client-secret',
            'services.github.redirect' => 'http://localhost/auth/github/callback',
        ]);
    }

    public function test_the_redirect_route_points_at_github(): void
    {
        $this->get('/auth/github/redirect')
            ->assertRedirectContains('github.com/login/oauth/authorize');
    }

    public function test_a_new_github_user_is_created_and_asked_to_enroll(): void
    {
        $this->fakeGithubUser();

        $this->get('/auth/github/callback')->assertRedirect('/two-factor');

        $user = User::firstWhere('github_id', '583231');

        $this->assertNotNull($user);
        $this->assertSame('octocat@example.com', $user->email);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_github_users_cannot_use_the_app_before_enrolling_when_enforcement_is_on(): void
    {
        config(['mfa.enforce_for_oauth' => true]);
        $this->fakeGithubUser();

        $this->get('/auth/github/callback');

        $this->get('/dashboard')->assertRedirect('/two-factor');
    }

    public function test_enforcement_can_be_switched_off(): void
    {
        config(['mfa.enforce_for_oauth' => false]);
        $this->fakeGithubUser();

        $this->get('/auth/github/callback')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_an_existing_email_account_is_linked_instead_of_duplicated(): void
    {
        $existing = User::factory()->create(['email' => 'octocat@example.com']);

        $this->fakeGithubUser();
        $this->get('/auth/github/callback');

        $this->assertSame(1, User::count());
        $this->assertSame('583231', $existing->fresh()->github_id);
    }

    public function test_a_github_user_with_two_factor_still_has_to_pass_the_challenge(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        User::factory()->github('583231')->withTwoFactor($secret)->create([
            'email' => 'octocat@example.com',
        ]);

        $this->fakeGithubUser();

        $this->get('/auth/github/callback')->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_a_cancelled_authorisation_returns_to_the_login_screen(): void
    {
        $this->get('/auth/github/callback?error=access_denied')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    private function fakeGithubUser(): void
    {
        $githubUser = (new SocialiteUser)->map([
            'id' => '583231',
            'nickname' => 'octocat',
            'name' => 'The Octocat',
            'email' => 'octocat@example.com',
            'avatar' => 'https://avatars.githubusercontent.com/u/583231?v=4',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($githubUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
    }
}
