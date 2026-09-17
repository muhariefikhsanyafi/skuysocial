<?php

namespace Tests\Feature\Auth;

use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_exposes_the_expected_accessible_auth_structure(): void
    {
        Config::set('services.turnstile.enabled', false);

        $response = $this->get(route('register'));
        $content = $response->getContent();
        $headingCount = preg_match_all('/<h1\b/i', $content, $headings);

        $response
            ->assertOk()
            ->assertSee('aria-labelledby="register-heading"', false)
            ->assertSee('action="'.route('register').'"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('id="name"', false)
            ->assertSee('name="name"', false)
            ->assertSee('type="text"', false)
            ->assertSee('autocomplete="name"', false)
            ->assertSee('id="username"', false)
            ->assertSee('name="username"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('pattern="^[a-zA-Z0-9_.]+$"', false)
            ->assertSee('id="email"', false)
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('id="password"', false)
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('id="password_confirmation"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('type="submit"', false);

        $this->assertSame(2, substr_count($content, 'class="skuy-auth-password-toggle"'));
        $this->assertSame(2, substr_count($content, 'type="password"'));
        $this->assertSame(2, substr_count($content, 'x-data="{ visible: false }"'));
        $this->assertSame(2, substr_count($content, 'x-bind:aria-pressed="visible"'));
        $this->assertSame(2, substr_count($content, 'aria-label="'.__('auth.common.show_password').'"'));
        $this->assertSame(2, substr_count($content, 'data-hide-label="'.__('auth.common.hide_password').'"'));
        $this->assertSame(1, $headingCount);
    }

    public function test_registration_field_errors_are_associated_with_their_inputs(): void
    {
        Config::set('services.turnstile.enabled', false);

        $this->followingRedirects()
            ->from(route('register'))
            ->post(route('register'), [
                'name' => '',
                'username' => 'invalid username',
                'email' => 'invalid',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="name-error"', false)
            ->assertSee('id="name-error"', false)
            ->assertSee('aria-describedby="username-hint username-error"', false)
            ->assertSee('id="username-error"', false)
            ->assertSee('aria-describedby="email-error"', false)
            ->assertSee('id="email-error"', false)
            ->assertSee('aria-describedby="password-error"', false)
            ->assertSee('id="password-error"', false);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'username' => 'Test_User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
            'locale' => 'id',
        ]);
        $response->assertSessionHas('locale', 'id');
        $this->assertLocaleCookieValue($response, 'id');
    }

    public function test_registered_user_survives_the_first_authenticated_request(): void
    {
        Config::set('session.driver', 'database');

        $password = 'registration-test-password';
        $this->withSession(['locale' => 'id']);
        $sessionIdBeforeRegistration = session()->getId();

        $response = $this->post('/register', [
            'name' => 'Session User',
            'username' => 'session_user',
            'email' => 'session@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $user = User::query()->where('email', 'session@example.com')->firstOrFail();

        $response
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertNotSame($sessionIdBeforeRegistration, session()->getId());
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame(ModerationState::Active->value, $user->moderation_state);
        $this->assertSame(0, $user->authSessionVersion());
        $this->assertSame(UserRole::User->value, $user->role);

        Auth::forgetGuards();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_initializes_account_security_state_without_schema_defaults(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->nullable()->default(null)->change();
            $table->string('moderation_state')->nullable()->default(null)->change();
            $table->unsignedBigInteger('moderation_state_version')->nullable()->default(null)->change();
            $table->unsignedBigInteger('auth_session_version')->nullable()->default(null)->change();
        });

        $password = 'registration-security-state-password';

        $response = $this->post('/register', [
            'name' => 'Security State User',
            'username' => 'security_state_user',
            'email' => 'security-state@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $user = User::query()->where('email', 'security-state@example.com')->firstOrFail();

        $response
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame(UserRole::User->value, $user->role);
        $this->assertSame(ModerationState::Active->value, $user->moderation_state);
        $this->assertSame(0, $user->moderationStateVersion());
        $this->assertSame(0, $user->authSessionVersion());

        Auth::forgetGuards();

        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect('/');

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registered_credentials_work_after_normal_logout(): void
    {
        $password = 'registration-login-test-password';

        $this->post('/register', [
            'name' => 'Login User',
            'username' => 'login_user',
            'email' => 'registration-login@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'registration-login@example.com')->firstOrFail();

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => $password,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0)
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        Auth::forgetGuards();
        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_registered_user_wrong_password_gets_the_generic_failure(): void
    {
        $password = 'registration-negative-test-password';

        $this->post('/register', [
            'name' => 'Negative Login User',
            'username' => 'negative_login_user',
            'email' => 'registration-negative@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'registration-negative@example.com')->firstOrFail();

        $this->post(route('logout'))->assertRedirect('/');

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-registration-password',
            ])
            ->assertSessionHasErrors(['email' => trans('auth.failed')])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_registration_persists_the_active_supported_locale(): void
    {
        $response = $this->withSession(['locale' => 'en'])->post('/register', [
            'name' => 'English User',
            'username' => 'english_user',
            'email' => 'english@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('locale', 'en');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'english@example.com',
            'locale' => 'en',
        ]);
        $this->assertLocaleCookieValue($response, 'en');
    }

    public function test_username_is_required_during_registration(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'required@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_username_must_be_unique_during_registration(): void
    {
        User::factory()->create(['username' => 'taken_user']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Another User',
            'username' => 'taken_user',
            'email' => 'another@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_mixed_case_username_is_stored_in_lowercase(): void
    {
        $response = $this->post('/register', [
            'name' => 'Mixed Case User',
            'username' => 'MiXeD_User',
            'email' => 'mixed@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'username' => 'mixed_user',
        ]);
    }

    public function test_case_variant_of_existing_username_is_rejected_by_validation(): void
    {
        User::factory()->create(['username' => 'taken_user']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Another User',
            'username' => 'Taken_User',
            'email' => 'another-case@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasErrors('username')
            ->assertRedirect('/register');

        $this->assertGuest();
    }

    public function test_case_collision_does_not_surface_as_a_database_exception(): void
    {
        User::factory()->create(['username' => 'taken_user']);

        $response = $this->from('/register')->post('/register', [
            'name' => 'Another User',
            'username' => 'TAKEN_USER',
            'email' => 'another-exception@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_registration_submissions_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/register', [
                'name' => 'Rate Limited User',
                'email' => "rate-limit-{$attempt}@example.com",
            ])->assertRedirect();
        }

        $this->post('/register', [
            'name' => 'Rate Limited User',
            'email' => 'rate-limit-6@example.com',
        ])->assertStatus(429);
    }

    private function assertLocaleCookieValue($response, string $locale): void
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('localization.cookie_name'));

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertSame(
            CookieValuePrefix::create(
                config('localization.cookie_name'),
                app('encrypter')->getKey(),
            ).$locale,
            Crypt::decrypt($cookie->getValue(), false),
        );
    }
}
