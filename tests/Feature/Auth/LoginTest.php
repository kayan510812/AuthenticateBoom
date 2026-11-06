<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public function test_google_only_account_is_pointed_to_google_when_using_a_password(): void
    {
        $user = User::factory()->google()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $message = session('errors')->first('email');
        $this->assertStringContainsString('Google', $message);
        $this->assertStringNotContainsString('GitHub', $message);
        $this->assertGuest();
    }

    public function test_login_is_blocked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear($this->loginThrottleKey($user->email));

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        RateLimiter::clear($this->loginThrottleKey($user->email));
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear($this->loginThrottleKey($user->email));

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');

        // The counter was reset, so four more failures still do not block the account.
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        RateLimiter::clear($this->loginThrottleKey($user->email));
    }

    public function test_the_login_block_applies_per_email_address(): void
    {
        $blocked = User::factory()->create();
        $other = User::factory()->create();
        RateLimiter::clear($this->loginThrottleKey($blocked->email));
        RateLimiter::clear($this->loginThrottleKey($other->email));

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $blocked->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $other->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($other);

        RateLimiter::clear($this->loginThrottleKey($blocked->email));
        RateLimiter::clear($this->loginThrottleKey($other->email));
    }

    public function test_the_two_factor_challenge_is_blocked_after_five_invalid_codes(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->create();
        RateLimiter::clear('two-factor:'.$user->getKey());

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post('/two-factor-challenge', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertSessionHasErrors('code');

        $this->assertGuest();

        RateLimiter::clear('two-factor:'.$user->getKey());
    }

    public function test_an_invalid_recovery_code_counts_towards_the_two_factor_limit(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user = User::factory()->withTwoFactor($secret)->create();
        RateLimiter::clear('two-factor:'.$user->getKey());

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/two-factor-challenge', ['recovery_code' => 'not-a-real-code'])
                ->assertSessionHasErrors('recovery_code');
        }

        $this->post('/two-factor-challenge', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertSessionHasErrors('code');

        $this->assertGuest();

        RateLimiter::clear('two-factor:'.$user->getKey());
    }

    public function test_a_pending_challenge_can_be_cancelled(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/two-factor-challenge');

        $this->post('/two-factor-challenge/cancel')->assertRedirect('/login');

        $this->get('/two-factor-challenge')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function loginThrottleKey(string $email): string
    {
        return Str::transliterate(Str::lower($email).'|127.0.0.1');
    }

    public function test_users_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }
}
