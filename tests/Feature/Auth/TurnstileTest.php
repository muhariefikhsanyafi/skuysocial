<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
            'services.turnstile.hostname' => 'skuysocial.com',
            'services.turnstile.verify_url' => self::VERIFY_URL,
        ]);
    }

    public function test_disabled_turnstile_keeps_registration_functional_without_http_call(): void
    {
        Config::set('services.turnstile.enabled', false);
        Http::fake();

        $response = $this->post('/register', $this->registrationData());

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_valid_registration_turnstile_allows_account_creation_without_remote_ip(): void
    {
        $this->fakeVerification('register');

        $response = $this->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'register-token',
        ]));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        Http::assertSent(function ($request) {
            return $request->url() === self::VERIFY_URL
                && $request['secret'] === 'test-secret-key'
                && $request['response'] === 'register-token'
                && ! array_key_exists('remoteip', $request->data());
        });
    }

    public function test_missing_registration_token_is_rejected_before_account_creation(): void
    {
        Http::fake();

        $response = $this->from('/register')->post('/register', $this->registrationData());

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
        Http::assertNothingSent();
    }

    public function test_non_string_registration_token_is_rejected_without_casting(): void
    {
        Http::fake();

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => ['not-a-token'],
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
        Http::assertNothingSent();
    }

    public function test_invalid_registration_token_is_rejected_before_account_creation(): void
    {
        $this->fakeVerification('register', ['success' => false]);

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'invalid-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
    }

    public function test_wrong_registration_hostname_is_rejected(): void
    {
        $this->fakeVerification('register', [
            'success' => true,
            'hostname' => 'attacker.example',
        ]);

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'wrong-host-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
    }

    public function test_wrong_registration_action_is_rejected(): void
    {
        $this->fakeVerification('login', [
            'success' => true,
            'hostname' => 'skuysocial.com',
        ]);

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'wrong-action-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
    }

    public function test_malformed_registration_response_is_rejected(): void
    {
        Http::fake([
            self::VERIFY_URL => Http::response(['success' => true]),
        ]);

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'malformed-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_http_failure_during_registration_is_rejected(): void
    {
        Http::fake([self::VERIFY_URL => Http::response([], 503)]);

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'http-failure-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
    }

    public function test_connection_or_timeout_failure_during_registration_is_rejected(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'timeout-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
    }

    public function test_missing_secret_fails_closed_without_http_call(): void
    {
        Config::set('services.turnstile.secret_key', null);
        Http::fake();

        $response = $this->from('/register')->post('/register', $this->registrationData([
            'cf-turnstile-response' => 'missing-secret-token',
        ]));

        $response->assertRedirect('/register')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'turnstile@example.com']);
        Http::assertNothingSent();
    }

    public function test_valid_login_turnstile_allows_authentication(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com', 'password' => Hash::make('password')]);
        $this->fakeVerification('login');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf-turnstile-response' => 'login-token',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_missing_login_token_is_rejected_before_authentication(): void
    {
        $user = User::factory()->create(['email' => 'login-missing@example.com', 'password' => Hash::make('password')]);
        Http::fake();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/login')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_invalid_login_token_is_rejected_before_authentication(): void
    {
        $user = User::factory()->create(['email' => 'login-invalid@example.com', 'password' => Hash::make('password')]);
        $this->fakeVerification('login', ['success' => false]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'cf-turnstile-response' => 'invalid-login-token',
        ]);

        $response->assertRedirect('/login')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertGuest();
    }

    public function test_login_rate_limit_remains_after_turnstile_verification(): void
    {
        $user = User::factory()->create(['email' => 'login-throttle@example.com', 'password' => Hash::make('password')]);
        $this->fakeVerification('login');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'cf-turnstile-response' => "login-token-{$attempt}",
            ])->assertRedirect();
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'cf-turnstile-response' => 'login-token-6',
        ])->assertRedirect()->assertSessionHasErrors('email');

        Http::assertSentCount(5);
    }

    public function test_valid_forgot_password_turnstile_allows_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->fakeVerification('forgot_password');

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
            'cf-turnstile-response' => 'forgot-token',
        ]);

        $response->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_missing_forgot_password_token_sends_no_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Http::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertRedirect('/forgot-password')->assertSessionHasErrors('cf-turnstile-response');
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_invalid_forgot_password_token_sends_no_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->fakeVerification('forgot_password', ['success' => false]);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
            'cf-turnstile-response' => 'invalid-forgot-token',
        ]);

        $response->assertRedirect('/forgot-password')->assertSessionHasErrors('cf-turnstile-response');
        Notification::assertNothingSent();
    }

    public function test_forgot_password_route_throttle_remains(): void
    {
        Notification::fake();
        $this->fakeVerification('forgot_password');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/forgot-password', [
                'email' => "forgot-throttle-{$attempt}@example.com",
                'cf-turnstile-response' => "forgot-token-{$attempt}",
            ])->assertRedirect();
        }

        $this->post('/forgot-password', [
            'email' => 'forgot-throttle-6@example.com',
            'cf-turnstile-response' => 'forgot-token-6',
        ])->assertStatus(429);

        Http::assertSentCount(5);
    }

    public function test_valid_reset_password_turnstile_allows_password_change(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::broker()->createToken($user);
        $this->fakeVerification('reset_password');

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'cf-turnstile-response' => 'reset-token',
        ]);

        $response->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_missing_reset_password_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $oldHash = $user->password;
        Http::fake();

        $response = $this->from('/reset-password/test-token')->post('/reset-password', [
            'token' => 'test-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/reset-password/test-token')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertSame($oldHash, $user->fresh()->password);
        Http::assertNothingSent();
    }

    public function test_invalid_reset_password_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $oldHash = $user->password;
        $this->fakeVerification('reset_password', ['success' => false]);

        $response = $this->from('/reset-password/test-token')->post('/reset-password', [
            'token' => 'test-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'cf-turnstile-response' => 'invalid-reset-token',
        ]);

        $response->assertRedirect('/reset-password/test-token')->assertSessionHasErrors('cf-turnstile-response');
        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_enabled_auth_views_render_action_and_site_key_without_secret(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('data-action="register"', false)
            ->assertSee('data-sitekey="test-site-key"', false)
            ->assertDontSee('test-secret-key', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('data-action="login"', false)
            ->assertDontSee('test-secret-key', false);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('data-action="forgot_password"', false)
            ->assertDontSee('test-secret-key', false);

        $this->get('/reset-password/test-token')
            ->assertOk()
            ->assertSee('data-action="reset_password"', false)
            ->assertDontSee('test-secret-key', false);
    }

    public function test_disabled_auth_views_do_not_render_turnstile_widget(): void
    {
        Config::set('services.turnstile.enabled', false);

        foreach (['/register', '/login', '/forgot-password', '/reset-password/test-token'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee('cf-turnstile', false);
        }
    }

    private function fakeVerification(string $action, array $overrides = []): void
    {
        Http::fake([
            self::VERIFY_URL => Http::response(array_merge([
                'success' => true,
                'hostname' => 'skuysocial.com',
                'action' => $action,
            ], $overrides)),
        ]);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Turnstile User',
            'username' => 'turnstile_user',
            'email' => 'turnstile@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }
}
