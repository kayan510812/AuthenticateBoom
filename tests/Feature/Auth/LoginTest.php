<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with GitHub');
    }

    public function test_user_without_two_factor_reaches_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_github_only_account_cannot_sign_in_with_a_password(): void
    {
        $user = User::factory()->github()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_with_two_factor_is_not_authenticated_until_the_challenge_passes(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor-challenge');

        // The password step alone must not authenticate the session.
        $this->assertGuest();

        $this->get('/dashboard')->assertRedirect('/login');

        $this->post('/two-factor-challenge', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_invalid_two_factor_code_keeps_the_user_out(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_two_factor_code_cannot_be_replayed(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->create();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/dashboard');

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $recoveryCode = $user->two_factor_recovery_codes[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recoveryCode, $user->fresh()->two_factor_recovery_codes);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])
            ->assertSessionHasErrors('recovery_code');

        $this->assertGuest();
    }

    public function test_the_challenge_expires(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->travel(config('mfa.challenge_lifetime') + 60)->seconds();

        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_users_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
