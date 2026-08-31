<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_account_and_lands_on_the_setup_screen(): void
    {
        $this->post('/register', [
            'name' => 'Kayan',
            'email' => 'kayan@example.com',
            'password' => 'wachtwoord-123',
            'password_confirmation' => 'wachtwoord-123',
        ])->assertRedirect('/two-factor');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'kayan@example.com']);
    }

    public function test_the_setup_screen_shows_a_qr_code_and_a_secret(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/two-factor');

        $response->assertOk()->assertSee('<svg', false);

        $this->assertNotEmpty(session('two_factor.pending_secret'));
    }

    public function test_the_secret_survives_a_page_reload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/two-factor');
        $first = session('two_factor.pending_secret');

        $this->actingAs($user)->get('/two-factor');

        $this->assertSame($first, session('two_factor.pending_secret'));
    }

    public function test_a_valid_code_enables_two_factor_and_shows_recovery_codes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/two-factor');
        $secret = session('two_factor.pending_secret');

        $this->actingAs($user)->post('/two-factor', [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertRedirect('/two-factor');

        $user->refresh();

        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertCount(config('mfa.recovery_codes'), $user->two_factor_recovery_codes);
        $this->assertNull(session('two_factor.pending_secret'));
    }

    public function test_an_invalid_code_does_not_enable_two_factor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/two-factor');

        $this->actingAs($user)->post('/two-factor', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_the_secret_is_stored_encrypted(): void
    {
        $user = User::factory()->withTwoFactor('ABCDEFGHIJKLMNOP')->create();

        $raw = $this->getConnection()->table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame('ABCDEFGHIJKLMNOP', $raw);
        $this->assertSame('ABCDEFGHIJKLMNOP', $user->fresh()->two_factor_secret);
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $old = $user->two_factor_recovery_codes;

        $this->actingAs($user)->post('/two-factor/recovery-codes')->assertRedirect('/two-factor');

        $this->assertNotEquals($old, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_password_user_can_disable_two_factor(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)->delete('/two-factor', ['password' => 'password'])
            ->assertRedirect('/two-factor');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_requires_the_current_password(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)->delete('/two-factor', ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_github_account_cannot_disable_two_factor_while_enforcement_is_on(): void
    {
        config(['mfa.enforce_for_oauth' => true]);
        $user = User::factory()->github()->withTwoFactor()->create();

        $this->actingAs($user)->delete('/two-factor')->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_a_google_account_cannot_disable_two_factor_while_enforcement_is_on(): void
    {
        config(['mfa.enforce_for_oauth' => true]);
        $user = User::factory()->google()->withTwoFactor()->create();

        $this->actingAs($user)->delete('/two-factor')->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }
}
