<?php

namespace Tests\Feature;

use App\Actions\TransitionUserModerationState;
use App\Enums\ModerationReason;
use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Http\Middleware\EnforceAccountSession;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModerationAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', $this->credentials($user));

        $response
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertAuthenticatedAs($user);
    }

    public function test_restricted_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $response = $this->post('/login', $this->credentials($user));

        $response
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertAuthenticatedAs($user);
    }

    public function test_suspended_user_login_is_denied_with_a_status_message(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);

        $this->post('/login', $this->credentials($user))
            ->assertSessionHasErrors(['email' => trans('moderation.login.suspended')]);

        $this->assertGuest();
    }

    public function test_banned_user_login_is_denied_with_a_status_message(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Banned->value,
        ]);

        $this->post('/login', $this->credentials($user))
            ->assertSessionHasErrors(['email' => trans('moderation.login.banned')]);

        $this->assertGuest();
    }

    public function test_suspended_login_message_uses_the_selected_guest_locale(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);

        $this
            ->withSession([config('localization.session_key', 'locale') => 'en'])
            ->post('/login', $this->credentials($user))
            ->assertSessionHasErrors([
                'email' => trans('moderation.login.suspended', [], 'en'),
            ]);

        $this->assertGuest();
    }

    public function test_invalid_state_user_login_is_denied_with_the_generic_failure(): void
    {
        $user = $this->corruptModerationState(User::factory()->create());

        $this->post('/login', $this->credentials($user))
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_suspended_user_with_a_wrong_password_gets_the_generic_failure(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_banned_user_with_a_wrong_password_gets_the_generic_failure(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Banned->value,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_nonexistent_account_gets_the_generic_failure(): void
    {
        $this->post('/login', [
            'email' => 'missing-account@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_registration_uses_active_defaults_and_ignores_moderation_input(): void
    {
        Notification::fake();
        Config::set('mail.default', 'array');

        $this->post('/register', [
            'name' => 'Moderation Default',
            'username' => 'moderation_default',
            'email' => 'moderation-default@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'moderation_state' => ModerationState::Banned->value,
            'auth_session_version' => 99,
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'moderation-default@example.com')->firstOrFail();

        $this->assertSame(ModerationState::Active->value, $user->moderation_state);
        $this->assertSame(0, $user->authSessionVersion());
    }

    public function test_active_legacy_session_without_a_version_is_retained_as_version_zero(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession($this->sessionFor($user, includeVersion: false))
            ->get('/dashboard');

        $response
            ->assertOk()
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 0);
        $this->assertAuthenticatedAs($user);
    }

    public function test_restricted_session_is_retained_for_authenticated_reads(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $this
            ->withSession($this->sessionFor($user))
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText(trans('moderation.restricted.notice_title'))
            ->assertSeeText(trans('moderation.restricted.notice_body'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_active_user_does_not_see_the_restricted_account_notice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSeeText(trans('moderation.restricted.notice_title'))
            ->assertDontSeeText(trans('moderation.restricted.notice_body'));
    }

    public function test_restricted_account_notice_uses_the_authenticated_users_locale(): void
    {
        $user = User::factory()->create([
            'locale' => 'en',
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText(trans('moderation.restricted.notice_title', [], 'en'))
            ->assertSeeText(trans('moderation.restricted.notice_body', [], 'en'));
    }

    public function test_suspended_existing_session_is_revoked_before_an_authenticated_route(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);

        $this
            ->withSession($this->sessionFor($user))
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_banned_existing_session_is_revoked_and_public_route_continues_as_guest(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Banned->value,
        ]);

        $this
            ->withSession($this->sessionFor($user))
            ->get('/')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_invalid_state_existing_session_is_revoked(): void
    {
        $user = $this->corruptModerationState(User::factory()->create());

        $this
            ->withSession($this->sessionFor($user))
            ->get('/')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_session_version_mismatch_is_revoked(): void
    {
        $user = User::factory()->create(['auth_session_version' => 2]);

        $this
            ->withSession($this->sessionFor($user, version: 1))
            ->get('/')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_revoked_session_clears_the_current_remember_me_cookie(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);
        $guard = Auth::guard('web');
        $recallerName = $guard->getRecallerName();
        $recaller = implode('|', [
            $user->getAuthIdentifier(),
            $user->getRememberToken(),
            $guard->hashPasswordForCookie($user->getAuthPassword()),
        ]);

        $this
            ->withSession($this->sessionFor($user))
            ->withCookie($recallerName, $recaller)
            ->get('/')
            ->assertOk()
            ->assertCookieExpired($recallerName);

        $this->assertGuest();
    }

    public function test_reactivation_requires_fresh_login_and_never_revives_an_old_session(): void
    {
        $actor = User::factory()->administrator()->create();
        $user = User::factory()->create();
        $oldSession = $this->sessionFor($user, version: 0);

        $this->transition()->handle(
            $actor,
            $user,
            ModerationState::Suspended,
            ModerationReason::SecurityAbuse,
            'Revoke existing sessions.',
        );
        $this->transition()->handle(
            $actor,
            $user,
            ModerationState::Active,
            ModerationReason::Other,
            'Manual reactivation.',
        );

        $this
            ->withSession($oldSession)
            ->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->flushSession();
        Auth::forgetGuards();

        $this->post('/login', $this->credentials($user))
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas(EnforceAccountSession::SESSION_VERSION_KEY, 1);
        $this->assertAuthenticatedAs($user);

        $this->flushSession();
        Auth::forgetGuards();

        $this
            ->withSession($oldSession)
            ->get('/')
            ->assertOk();
        $this->assertGuest();
    }

    public function test_restricted_user_can_log_out_safely(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_admin_and_policy_access_share_the_usable_administrator_predicate(): void
    {
        $panel = Filament::getPanel('admin');
        $policy = app(UserPolicy::class);
        $active = User::factory()->administrator()->create();
        $restricted = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $suspended = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Suspended->value,
        ]);
        $banned = User::factory()->administrator()->create([
            'moderation_state' => ModerationState::Banned->value,
        ]);
        $unverified = User::factory()->administrator()->unverified()->create();
        $ordinary = User::factory()->create();
        $invalid = (new User)->forceFill([
            'role' => UserRole::Administrator->value,
            'email_verified_at' => now(),
            'moderation_state' => 'invalid_state',
        ]);

        $this->assertTrue($active->canAccessPanel($panel));
        $this->assertTrue($policy->viewAny($active));

        foreach ([$restricted, $suspended, $banned, $unverified, $ordinary, $invalid] as $denied) {
            $this->assertFalse($denied->canAccessPanel($panel));
            $this->assertFalse($policy->viewAny($denied));
        }
    }

    public function test_restricted_administrator_is_forbidden_from_admin_http_access(): void
    {
        $administrator = User::factory()->administrator()->create([
            'locale' => 'en',
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertForbidden()
            ->assertSeeText(trans('moderation.forbidden.title', [], 'en'))
            ->assertSeeText(trans('moderation.restricted.notice_title', [], 'en'))
            ->assertSeeText(trans('moderation.restricted.denial_body', [], 'en'));
    }

    public function test_unrelated_forbidden_response_stays_generic_for_a_restricted_user(): void
    {
        Route::get('/_test/unrelated-forbidden', fn () => abort(403))
            ->middleware('auth');
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $this->actingAs($user)
            ->get('/_test/unrelated-forbidden')
            ->assertForbidden()
            ->assertSeeText(trans('moderation.forbidden.generic_body'))
            ->assertDontSeeText(trans('moderation.restricted.denial_body'));
    }

    public function test_restricted_non_administrator_admin_denial_stays_generic(): void
    {
        $user = User::factory()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden()
            ->assertSeeText(trans('moderation.forbidden.generic_body'))
            ->assertDontSeeText(trans('moderation.restricted.notice_title'))
            ->assertDontSeeText(trans('moderation.restricted.denial_body'));
    }

    public function test_guest_forbidden_response_does_not_expose_account_restriction(): void
    {
        Route::get('/_test/guest-forbidden', fn () => abort(403));

        $this->get('/_test/guest-forbidden')
            ->assertForbidden()
            ->assertSeeText(trans('moderation.forbidden.generic_body'))
            ->assertSeeText(trans('moderation.forbidden.back_to_home'))
            ->assertSee('href="'.url('/').'"', false)
            ->assertDontSeeText(trans('moderation.restricted.notice_title'))
            ->assertDontSeeText(trans('moderation.restricted.denial_body'));
    }

    public function test_non_authenticating_admin_states_are_revoked_before_admin_http_access(): void
    {
        foreach ([ModerationState::Suspended, ModerationState::Banned] as $state) {
            $administrator = User::factory()->administrator()->create([
                'moderation_state' => $state->value,
            ]);

            $this
                ->withSession($this->sessionFor($administrator))
                ->get('/admin')
                ->assertRedirect('/admin/login');
            $this->assertGuest();
            $this->flushSession();
            Auth::forgetGuards();
        }
    }

    public function test_invalid_state_administrator_is_revoked_before_admin_http_access(): void
    {
        $administrator = $this->corruptModerationState(
            User::factory()->administrator()->create(),
        );

        $this
            ->withSession($this->sessionFor($administrator))
            ->get('/admin')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    }

    /**
     * @return array{email: string, password: string}
     */
    private function credentials(User $user): array
    {
        return [
            'email' => $user->email,
            'password' => 'password',
        ];
    }

    /**
     * @return array<string, int>
     */
    private function sessionFor(User $user, int $version = 0, bool $includeVersion = true): array
    {
        $session = [
            Auth::guard('web')->getName() => $user->getAuthIdentifier(),
        ];

        if ($includeVersion) {
            $session[EnforceAccountSession::SESSION_VERSION_KEY] = $version;
        }

        return $session;
    }

    private function transition(): TransitionUserModerationState
    {
        return app(TransitionUserModerationState::class);
    }

    private function corruptModerationState(User $user): User
    {
        DB::statement('PRAGMA ignore_check_constraints = ON');

        try {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['moderation_state' => 'invalid_state']);
        } finally {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }

        return $user->refresh();
    }
}
