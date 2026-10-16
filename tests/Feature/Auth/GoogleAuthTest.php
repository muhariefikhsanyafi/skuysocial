<?php

namespace Tests\Feature\Auth;

use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\TwoFactorAuthentication;
use App\Services\TwoFactorLoginChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google.enabled', true);
        Config::set('services.google.client_id', 'test-client.apps.googleusercontent.com');
        Config::set('services.google.client_secret', 'test-secret-never-production');
        Config::set('services.google.redirect', 'http://localhost/auth/google/callback');
        Http::preventStrayRequests();
    }

    public function test_disabled_google_provider_hides_buttons_and_refuses_redirect(): void
    {
        Config::set('services.google.enabled', false);

        $this->get(route('login'))->assertOk()->assertDontSee('data-google-auth', false);
        $this->get(route('register'))->assertOk()->assertDontSee('data-google-auth', false);
        $this->get(route('auth.google.redirect'))->assertNotFound();
    }

    public function test_google_button_is_accessible_on_login_and_registration(): void
    {
        foreach (['login', 'register'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('data-google-auth', false)
                ->assertSee('href="'.route('auth.google.redirect').'"', false);
        }
    }

    public function test_authorization_redirect_contains_pkce_state_and_fixed_google_endpoint(): void
    {
        $response = $this->get(route('auth.google.redirect'))->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
        $this->assertSame('S256', $parameters['code_challenge_method']);
        $this->assertSame('openid email profile', $parameters['scope']);
        $this->assertSame('http://localhost/auth/google/callback', $parameters['redirect_uri']);
        $this->assertSame(64, strlen($parameters['state']));
        $this->assertNotSame('', $parameters['code_challenge']);
    }

    public function test_new_google_user_must_choose_their_name_and_permanent_username_before_account_creation(): void
    {
        $this->fakeGoogle('google-sub-123', 'new@gmail.com', true, 'Google Suggested Name');

        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'mock-code']))
            ->assertRedirect(route('auth.google.complete'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

        $this->get(route('auth.google.complete'))
            ->assertOk()
            ->assertSee('data-google-complete', false)
            ->assertSee('Google Suggested Name')
            ->assertSee('new@gmail.com')
            ->assertSee('name="username"', false)
            ->assertDontSee('name="password"', false);

        $this->post(route('auth.google.complete.store'), [
            'name' => 'My Chosen Name',
            'username' => 'My_Chosen.Name',
            'email' => 'injected@gmail.com',
            'google_sub' => 'attacker-sub',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('google_sub', 'google-sub-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('My Chosen Name', $user->name);
        $this->assertSame('my_chosen.name', $user->username);
        $this->assertSame('new@gmail.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(UserRole::User->value, $user->role);
        $this->assertSame(ModerationState::Active->value, $user->moderation_state);
        $this->assertSame(0, $user->authSessionVersion());
        $this->assertNull(session('google_oauth.pending_registration'));

        $this->post(route('logout'))->assertRedirect('/');
        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'mock-code']))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::query()->where('google_sub', 'google-sub-123')->count());
        $this->assertSame('my_chosen.name', $user->fresh()->username);
    }

    public function test_google_completion_rejects_duplicate_or_invalid_usernames_without_creating_account(): void
    {
        // Validate multiple form errors without exhausting the route's real-world HTTP throttle.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        User::factory()->create(['username' => 'taken_name']);
        $this->fakeGoogle('candidate-sub', 'candidate@gmail.com', true, 'Candidate');
        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('auth.google.complete'));

        $this->post(route('auth.google.complete.store'), [
            'name' => 'Candidate',
            'username' => 'Taken_Name',
        ])->assertSessionHasErrors('username');

        $this->post(route('auth.google.complete.store'), [
            'name' => 'Candidate',
            'username' => 'invalid username',
        ])->assertSessionHasErrors('username');

        $this->post(route('auth.google.complete.store'), [
            'name' => '',
            'username' => 'valid_name',
        ])->assertSessionHasErrors('name');

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
        $this->assertNull(User::query()->where('google_sub', 'candidate-sub')->first());

        $this->post(route('auth.google.complete.store'), [
            'name' => 'Valid Display Name',
            'username' => 'valid_name',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame('valid_name', User::query()->where('google_sub', 'candidate-sub')->firstOrFail()->username);
    }

    public function test_google_completion_requires_a_fresh_verified_oauth_session_and_cannot_replay(): void
    {
        $this->get(route('auth.google.complete'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->post(route('auth.google.complete.store'), [
            'name' => 'Forged User',
            'username' => 'forged',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();

        $this->fakeGoogle('expiring-google-sub', 'expired@gmail.com');
        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('auth.google.complete'));
        $pending = session('google_oauth.pending_registration');
        $this->withSession(['google_oauth.pending_registration' => [
            ...$pending,
            'created_at' => time() - 601,
        ]]);
        $this->post(route('auth.google.complete.store'), [
            'name' => 'Expired',
            'username' => 'expired_google',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertNull(session('google_oauth.pending_registration'));
    }

    public function test_cancelling_google_onboarding_does_not_create_an_account(): void
    {
        $this->fakeGoogle('cancel-google-sub', 'cancel@gmail.com');
        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('auth.google.complete'));

        $this->post(route('auth.google.complete.cancel'))->assertRedirect(route('login'));
        $this->get(route('auth.google.complete'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_completion_refuses_accounts_created_by_another_session_in_the_meantime(): void
    {
        $this->fakeGoogle('pending-sub', 'race@gmail.com');
        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('auth.google.complete'));

        User::factory()->create(['email' => 'race@gmail.com']);
        $this->post(route('auth.google.complete.store'), [
            'name' => 'Race Candidate',
            'username' => 'racer',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertNull(session('google_oauth.pending_registration'));
    }

    public function test_google_does_not_claim_preexisting_password_account_by_email(): void
    {
        $passwordAccount = User::factory()->create(['email' => 'existing@gmail.com']);
        $this->fakeGoogle('different-google-sub', 'existing@gmail.com');
        $state = $this->start();

        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'mock-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($passwordAccount->fresh()->google_sub);
        $this->assertSame(1, User::query()->where('email', 'existing@gmail.com')->count());
    }

    public function test_google_rejects_missing_state_replay_and_unverified_email(): void
    {
        $this->fakeGoogle('no-verified-email', 'unverified@gmail.com', false);

        $this->get(route('auth.google.callback', ['state' => 'invalid', 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $state = $this->start();
        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertSame(0, User::query()->count());
    }

    public function test_google_login_still_blocks_suspended_users(): void
    {
        $account = User::factory()->create([
            'google_sub' => 'suspended-sub',
            'moderation_state' => ModerationState::Suspended->value,
        ]);
        $this->fakeGoogle('suspended-sub', $account->email);
        $state = $this->start();

        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_login_cannot_bypass_existing_two_factor_challenge(): void
    {
        $user = User::factory()->create(['google_sub' => 'two-factor-google']);
        $security = new TwoFactorAuthentication();
        $security->user_id = $user->getKey();
        $security->secret = 'JBSWY3DPEHPK3PXP';
        $security->enabled_at = now();
        $security->save();

        $this->fakeGoogle('two-factor-google', $user->email);
        $state = $this->start();

        $this->get(route('auth.google.callback', ['state' => $state, 'code' => 'code']))
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->assertNotNull(app(TwoFactorLoginChallengeService::class)->active(request()));
    }

    public function test_existing_user_can_link_matching_google_only_after_password_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'myaccount@gmail.com']);

        $this->actingAs($user)->get(route('auth.google.link'))
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->fakeGoogle('linked-google-123', 'myaccount@gmail.com');
        $response = $this->get(route('auth.google.link'))->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $params);

        $this->get(route('auth.google.callback', ['state' => $params['state'], 'code' => 'code']))
            ->assertRedirect(route('user.edit', ['username' => $user->username]));

        $this->assertSame('linked-google-123', $user->fresh()->google_sub);
        $this->assertAuthenticatedAs($user);
    }

    public function test_link_refuses_different_google_email(): void
    {
        $user = User::factory()->create(['email' => 'owner@gmail.com']);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->fakeGoogle('unrelated-sub', 'not-owner@gmail.com');

        $response = $this->get(route('auth.google.link'))->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $params);

        $this->get(route('auth.google.callback', ['state' => $params['state'], 'code' => 'code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertNull($user->fresh()->google_sub);
    }

    private function start(): string
    {
        $response = $this->get(route('auth.google.redirect'))->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $params);

        return $params['state'];
    }

    private function fakeGoogle(
        string $subject,
        string $email,
        bool $verified = true,
        string $name = 'Google User',
    ): void {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'mock-token'], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => $subject,
                'email' => $email,
                'email_verified' => $verified,
                'name' => $name,
            ], 200),
        ]);
    }
}
