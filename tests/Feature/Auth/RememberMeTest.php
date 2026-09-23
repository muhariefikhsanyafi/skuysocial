<?php

namespace Tests\Feature\Auth;

use App\Actions\TransitionUserModerationState;
use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_checkbox_is_localized_accessible_and_unchecked_by_default(): void
    {
        foreach ([
            'id' => 'Ingat Saya selama 30 hari',
            'en' => 'Remember me for 30 days',
        ] as $locale => $label) {
            $response = $this->withSession(['locale' => $locale])->get(route('login'));
            $html = $response->getContent();
            $this->assertIsString($html);

            $response
                ->assertOk()
                ->assertSeeText($label)
                ->assertSee('type="checkbox"', false)
                ->assertSee('id="remember"', false)
                ->assertSee('name="remember"', false)
                ->assertSee('value="1"', false)
                ->assertSee('for="remember"', false);

            $this->assertSame(1, preg_match('/<input\b[^>]*\bid="remember"[^>]*>/i', $html, $matches));
            $this->assertDoesNotMatchRegularExpression('/\bchecked\b/i', $matches[0]);
        }
    }

    public function test_unchecked_login_does_not_issue_a_remember_cookie_or_change_session_lifetime(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->getRememberToken();
        $sessionLifetime = config('session.lifetime');

        $response = $this->post(route('login'), $this->credentials($user));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($this->cookie($response, $this->recallerName()));
        $this->assertSame($rememberToken, $user->fresh()->getRememberToken());
        $this->assertSame($sessionLifetime, config('session.lifetime'));
    }

    public function test_checked_login_issues_a_30_day_recaller_and_regenerates_the_session(): void
    {
        $user = User::factory()->create();
        $sessionLifetime = config('session.lifetime');
        $this->withSession(['locale' => 'id']);
        $sessionIdBeforeLogin = session()->getId();

        $response = $this->post(route('login'), $this->credentials($user, remember: true));
        $cookie = $this->cookie($response, $this->recallerName());

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertGreaterThan(0, $cookie->getExpiresTime());
        $this->assertEqualsWithDelta(now()->timestamp + (43_200 * 60), $cookie->getExpiresTime(), 5);
        $this->assertSame(43_200, config('auth.guards.web.remember'));
        $this->assertSame($sessionLifetime, config('session.lifetime'));
        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_cookie_authenticates_after_the_session_is_cleared(): void
    {
        $user = User::factory()->create();
        $login = $this->post(route('login'), $this->credentials($user, remember: true));
        $cookie = $this->cookie($login, $this->recallerName());

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->flushSession();
        Auth::forgetGuards();

        $response = $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/dashboard');

        $response->assertOk()->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertAuthenticatedAs($user);
        $this->assertInstanceOf(SessionGuard::class, Auth::guard('web'));
        $this->assertTrue(Auth::guard('web')->viaRemember());
    }

    public function test_logout_expires_the_recaller_and_invalidates_its_old_credential(): void
    {
        $user = User::factory()->create();
        $login = $this->post(route('login'), $this->credentials($user, remember: true));
        $cookie = $this->cookie($login, $this->recallerName());
        $rememberToken = $user->fresh()->getRememberToken();

        $this->assertInstanceOf(Cookie::class, $cookie);

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->post(route('logout'))
            ->assertRedirect('/')
            ->assertCookieExpired($cookie->getName());

        $this->assertGuest();
        $this->assertNotSame($rememberToken, $user->fresh()->getRememberToken());

        $this->flushSession();
        Auth::forgetGuards();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_wrong_credentials_keep_the_generic_error_and_do_not_issue_a_recaller(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->getRememberToken();

        $response = $this->from(route('login'))
            ->post(route('login'), $this->credentials($user, password: 'wrong-password', remember: true));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
        $this->assertNull($this->cookie($response, $this->recallerName()));
        $this->assertSame($rememberToken, $user->fresh()->getRememberToken());
    }

    public function test_suspended_and_banned_logins_keep_their_messages_without_a_recaller(): void
    {
        foreach ([
            [ModerationState::Suspended, trans('moderation.login.suspended')],
            [ModerationState::Banned, trans('moderation.login.banned')],
        ] as [$state, $expectedMessage]) {
            $user = User::factory()->create(['moderation_state' => $state->value]);
            $rememberToken = $user->getRememberToken();

            $response = $this->from(route('login'))
                ->post(route('login'), $this->credentials($user, remember: true));

            $response
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => $expectedMessage]);

            $this->assertGuest();
            $this->assertNull($this->cookie($response, $this->recallerName()));
            $this->assertSame($rememberToken, $user->fresh()->getRememberToken());
        }
    }

    public function test_remembered_restricted_administrator_retains_read_only_and_admin_denial(): void
    {
        $user = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $login = $this->post(route('login'), $this->credentials($user, remember: true));
        $cookie = $this->cookie($login, $this->recallerName());

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->flushSession();
        Auth::forgetGuards();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText(trans('moderation.restricted.notice_title'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard('web')->viaRemember());

        $this->post(route('posts.store'), ['content' => 'This write must remain blocked.'])
            ->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_suspension_revokes_the_session_and_invalidates_a_previously_issued_recaller(): void
    {
        $actor = User::factory()->administrator()->create();
        $user = User::factory()->create();
        $login = $this->post(route('login'), $this->credentials($user, remember: true));
        $cookie = $this->cookie($login, $this->recallerName());
        $originalRememberToken = $user->fresh()->getRememberToken();

        $this->assertInstanceOf(Cookie::class, $cookie);

        app(TransitionUserModerationState::class)->handle(
            $actor,
            $user,
            ModerationState::Suspended,
            ModerationReason::SecurityAbuse,
            'Invalidate sessions and remembered authentication.',
        );

        $this->assertSame(1, $user->fresh()->authSessionVersion());
        $this->assertNotSame($originalRememberToken, $user->fresh()->getRememberToken());

        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->flushSession();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reactivation_does_not_revive_an_old_recaller_but_fresh_remember_login_works(): void
    {
        $actor = User::factory()->administrator()->create();
        $user = User::factory()->create();
        $login = $this->post(route('login'), $this->credentials($user, remember: true));
        $oldCookie = $this->cookie($login, $this->recallerName());

        $this->assertInstanceOf(Cookie::class, $oldCookie);

        $transition = app(TransitionUserModerationState::class);
        $transition->handle(
            $actor,
            $user,
            ModerationState::Suspended,
            ModerationReason::SecurityAbuse,
            'Revoke remembered authentication.',
        );
        $transition->handle(
            $actor,
            $user,
            ModerationState::Active,
            ModerationReason::Other,
            'Reactivate without restoring old remembered credentials.',
        );

        $this->flushSession();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($oldCookie->getName(), $oldCookie->getValue())
            ->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $freshLogin = $this->post(route('login'), $this->credentials($user, remember: true));
        $newCookie = $this->cookie($freshLogin, $this->recallerName());
        $this->assertInstanceOf(Cookie::class, $newCookie);

        $this->flushSession();
        Auth::forgetGuards();
        $response = $this->withUnencryptedCookie($newCookie->getName(), $newCookie->getValue())
            ->get('/dashboard');

        $response->assertOk()->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 1);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard('web')->viaRemember());
    }

    /**
     * @return array{email: string, password: string, remember?: string}
     */
    private function credentials(User $user, string $password = 'password', bool $remember = false): array
    {
        return [
            'email' => $user->email,
            'password' => $password,
            ...($remember ? ['remember' => '1'] : []),
        ];
    }

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    private function cookie(TestResponse $response, string $name): ?Cookie
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === $name);

        return $cookie instanceof Cookie ? $cookie : null;
    }
}
