<?php

namespace Tests\Feature\Auth;

use App\Enums\ModerationState;
use App\Models\TwoFactorLoginChallenge;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use App\Services\TwoFactorLoginChallengeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_security_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pengaturan/keamanan/2fa')
            ->assertRedirect('/confirm-password')
            ->assertSessionHas('url.intended', '/pengaturan/keamanan/2fa');
    }

    public function test_two_factor_setup_screen_uses_a_flat_content_section(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get('/pengaturan/keamanan/2fa')
            ->assertOk();

        $response->assertSee('<section class="skuy-settings-panel">', false)
            ->assertDontSee(
                'rounded-[var(--radius-large)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-5 sm:p-8',
                false,
            );
    }

    public function test_invalid_and_malformed_codes_are_generic_rate_limited_and_not_locked_permanently(): void
    {
        Config::set('session.driver', 'database');
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor/challenge');
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $this->assertTrue($sessionCookie !== null);
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
        Auth::forgetGuards();

        $google2fa = app(Google2FA::class);
        $currentStep = intdiv(now()->timestamp, 30);
        $acceptedCodes = array_map(
            fn (int $step): string => $google2fa->oathTotp($secret, $step),
            [$currentStep - 1, $currentStep, $currentStep + 1],
        );
        $numericInvalid = '000000';
        while (in_array($numericInvalid, $acceptedCodes, true)) {
            $numericInvalid = str_pad((string) ((int) $numericInvalid + 1), 6, '0', STR_PAD_LEFT);
        }

        foreach (['12x456', $numericInvalid, 'ABCDEF', '12345', 'NOT-A-RECOVERY-CODE'] as $invalidCode) {
            $this->from('/two-factor/challenge')
                ->post('/two-factor/challenge', ['code' => $invalidCode])
                ->assertRedirect('/two-factor/challenge')
                ->assertSessionHasErrors('code');
        }

        $this->from('/two-factor/challenge')
            ->post('/two-factor/challenge', ['code' => '123456'])
            ->assertTooManyRequests();
        $this->assertGuest();

        $this->travel(61)->seconds();
        $validCode = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->post('/two-factor/challenge', ['code' => $validCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_challenge_accepts_one_step_ahead_within_the_drift_window(): void
    {
        $baseTime = CarbonImmutable::createFromTimestamp(1_800_000_000);
        $this->travelTo($baseTime);
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $currentStep = intdiv($baseTime->timestamp, 30);
        $this->startLoginChallenge($user);

        $oneStepAheadCode = app(Google2FA::class)->oathTotp($secret, $currentStep + 1);
        $this->post('/two-factor/challenge', ['code' => $oneStepAheadCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_challenge_accepts_the_previous_non_replayed_step_at_the_drift_boundary(): void
    {
        $baseTime = CarbonImmutable::createFromTimestamp(1_800_000_000);
        $this->travelTo($baseTime);
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $currentStep = intdiv($baseTime->timestamp, 30);

        $this->startLoginChallenge($user);
        $this->travelTo($baseTime->addSeconds(60));
        $previousStepCode = app(Google2FA::class)->oathTotp($secret, $currentStep + 1);
        $this->post('/two-factor/challenge', ['code' => $previousStepCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_challenge_rejects_codes_outside_the_configured_drift_window(): void
    {
        $baseTime = CarbonImmutable::createFromTimestamp(1_800_000_000);
        $this->travelTo($baseTime);
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $currentStep = intdiv($baseTime->timestamp, 30);
        $this->startLoginChallenge($user);

        $google2fa = app(Google2FA::class);
        $acceptedCodes = array_map(
            fn (int $step): string => $google2fa->oathTotp($secret, $step),
            [$currentStep - 1, $currentStep, $currentStep + 1],
        );
        $outsideStep = $currentStep + 2;
        $outsideStepCode = $google2fa->oathTotp($secret, $outsideStep);
        while (in_array($outsideStepCode, $acceptedCodes, true)) {
            $outsideStepCode = $google2fa->oathTotp($secret, ++$outsideStep);
        }

        $this->from('/two-factor/challenge')
            ->post('/two-factor/challenge', ['code' => $outsideStepCode])
            ->assertRedirect('/two-factor/challenge')
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_unverified_users_cannot_open_two_factor_management(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get('/pengaturan/keamanan/2fa')
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseMissing('user_two_factor_authentications', ['user_id' => $user->id]);
    }

    public function test_suspended_users_cannot_open_two_factor_management(): void
    {
        $user = User::factory()->create(['moderation_state' => ModerationState::Suspended->value]);

        $this->actingAs($user)
            ->withSession(['auth_session_version' => 0, 'auth.password_confirmed_at' => now()->timestamp])
            ->get('/pengaturan/keamanan/2fa')
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('user_two_factor_authentications', ['user_id' => $user->id]);
    }

    public function test_banned_users_cannot_open_two_factor_management(): void
    {
        $user = User::factory()->create(['moderation_state' => ModerationState::Banned->value]);

        $this->actingAs($user)
            ->withSession(['auth_session_version' => 0, 'auth.password_confirmed_at' => now()->timestamp])
            ->get('/pengaturan/keamanan/2fa')
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('user_two_factor_authentications', ['user_id' => $user->id]);
    }

    public function test_totp_challenge_is_rate_limited_per_ip_across_pending_sessions(): void
    {
        for ($attempt = 1; $attempt <= 21; $attempt++) {
            $user = User::factory()->create();
            $this->enableTwoFactor($user);
            $this->startLoginChallenge($user);

            $response = $this->from('/two-factor/challenge')
                ->post('/two-factor/challenge', ['code' => 'not-a-code']);

            if ($attempt <= 20) {
                $response->assertRedirect('/two-factor/challenge');
            } else {
                $response->assertTooManyRequests();
            }
        }
    }

    public function test_totp_attempt_limit_applies_across_sessions_for_the_same_account(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $this->startLoginChallenge($user);
            $response = $this->from('/two-factor/challenge')
                ->post('/two-factor/challenge', ['code' => 'not-a-code']);

            if ($attempt <= 10) {
                $response->assertRedirect('/two-factor/challenge');
            } else {
                $response->assertTooManyRequests();
            }
        }

        $this->assertDatabaseCount('user_two_factor_login_challenges', 1);
    }

    public function test_intended_login_destination_drops_query_data_and_rejects_external_hosts(): void
    {
        $request = $this->pendingRequest();
        $challenges = app(TwoFactorLoginChallengeService::class);

        $request->session()->put('url.intended', '/feed?search=private&token=fake-value');
        $this->assertSame('/feed', $challenges->safeIntendedUrl($request));
        $request->session()->put('url.intended', 'https://outside.invalid/feed');
        $this->assertNull($challenges->safeIntendedUrl($request));
    }

    public function test_only_one_pending_session_per_account_and_a_totp_step_cannot_be_replayed(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $service = app(TwoFactorLoginChallengeService::class);
        $firstRequest = $this->pendingRequest();
        $secondRequest = $this->pendingRequest();

        $service->begin($user, $firstRequest, false, 'web', null);
        $service->begin($user, $secondRequest, false, 'web', null);
        $this->travel(31)->seconds();
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));

        $firstResult = $service->complete($firstRequest, $code);
        $secondResult = $service->complete($secondRequest, $code);
        $thirdRequest = $this->pendingRequest();
        $service->begin($user, $thirdRequest, false, 'web', null);
        $replayResult = $service->complete($thirdRequest, $code);

        $this->assertTrue($firstResult === null);
        $this->assertTrue($secondResult !== null);
        $this->assertTrue($replayResult === null);
    }

    public function test_canceling_the_challenge_clears_pending_state_without_remembering_the_user(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $login = $this->startLoginChallenge($user, remember: true);

        $this->assertFalse($this->hasRecallerCookie($login));
        $this->from('/two-factor/challenge')
            ->post('/two-factor/cancel')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseCount('user_two_factor_login_challenges', 0);
    }

    public function test_expired_challenge_cannot_authenticate_or_remember_the_user(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $login = $this->startLoginChallenge($user, remember: true);
        $this->assertFalse($this->hasRecallerCookie($login));
        TwoFactorLoginChallenge::query()
            ->where('user_id', $user->id)
            ->update(['expires_at' => now()->subSecond()]);

        $this->get('/two-factor/challenge')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('user_two_factor_login_challenges', 0);
    }

    public function test_password_update_rotates_authentication_state_and_invalidates_pending_totp_challenges(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $this->authenticateWithTotp($user, $secret);
        $sessionVersion = $user->fresh()->authSessionVersion();
        $rememberToken = $user->fresh()->getRememberToken();
        $pendingRequest = $this->pendingRequest();
        app(TwoFactorLoginChallengeService::class)->begin($user->fresh(), $pendingRequest, false, 'web', null);

        $updateResponse = $this->put('/password', [
            'current_password' => 'password',
            'password' => 'updated-password',
            'password_confirmation' => 'updated-password',
        ])->assertSessionHasNoErrors();

        $this->assertSame($sessionVersion + 1, $user->fresh()->authSessionVersion());
        $this->assertTrue($rememberToken !== $user->fresh()->getRememberToken());
        $updateResponse->assertSessionHas('auth_session_version', $sessionVersion + 1);

        $this->travel(31)->seconds();
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->assertTrue(app(TwoFactorLoginChallengeService::class)->complete($pendingRequest, $code) === null);
    }

    public function test_two_factor_challenge_follows_the_pending_accounts_supported_locale(): void
    {
        $cases = [
            'id' => ['locale' => 'en', 'expected' => 'Verifikasi dua faktor', 'opposite' => 'Two-factor verification'],
            'en' => ['locale' => 'id', 'expected' => 'Two-factor verification', 'opposite' => 'Verifikasi dua faktor'],
        ];

        foreach ($cases as $accountLocale => $copy) {
            $user = User::factory()->create(['locale' => $accountLocale]);
            $this->enableTwoFactor($user);
            $this->withSession(['locale' => $copy['locale']]);
            $login = $this->startLoginChallenge($user);
            $sessionCookie = $login->getCookie(config('session.cookie'));
            $this->assertTrue($sessionCookie !== null);
            $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
            Auth::forgetGuards();

            $challenge = $this->get('/two-factor/challenge')
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertSeeText($copy['expected']);
            $content = $challenge->getContent();
            $this->assertTrue((bool) preg_match('/<html\\s+lang="'.preg_quote($accountLocale, '/').'"/', $content));
            $this->assertFalse(str_contains($content, $copy['opposite']));
        }
    }

    public function test_account_security_surface_is_localized_from_the_user_preference(): void
    {
        $cases = [
            'en' => ['other' => 'id', 'heading' => 'Two-factor authentication', 'status' => 'Disabled'],
            'id' => ['other' => 'en', 'heading' => 'Autentikasi dua faktor', 'status' => 'Nonaktif'],
        ];

        foreach ($cases as $locale => $copy) {
            $user = User::factory()->create(['locale' => $locale]);
            Auth::forgetGuards();
            $response = $this->actingAs($user)
                ->withSession([
                    'auth_session_version' => $user->authSessionVersion(),
                    'auth.password_confirmed_at' => now()->timestamp,
                    'locale' => $copy['other'],
                ])
                ->get('/pengaturan/keamanan/2fa')
                ->assertOk();

            $content = $response->getContent();
            $this->assertTrue(str_contains($content, $copy['heading']));
            $this->assertTrue(str_contains($content, $copy['status']));
            $this->assertTrue((bool) preg_match('/<html\\s+lang="'.preg_quote($locale, '/').'"/', $content));
        }
    }

    public function test_password_confirmation_guesses_are_rate_limited_without_permanent_lockout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/confirm-password', ['password' => 'incorrect-password'])
                ->assertSessionHasErrors('password');
        }

        $this->post('/confirm-password', ['password' => 'incorrect-password'])->assertTooManyRequests();
        $this->assertAuthenticatedAs($user);
        $this->travel(61)->seconds();
        $this->post('/confirm-password', ['password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_expired_confirmation_from_a_sensitive_post_returns_to_security_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->subSeconds(301)->timestamp])
            ->post('/pengaturan/keamanan/2fa/setup')
            ->assertRedirect('/confirm-password');

        $this->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect('/pengaturan/keamanan/2fa');
    }

    public function test_recent_password_confirmation_expires_after_five_minutes(): void
    {
        $user = User::factory()->create();
        $oldConfirmation = now()->subSeconds(301)->timestamp;

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => $oldConfirmation])
            ->post('/pengaturan/keamanan/2fa/setup')
            ->assertRedirect('/confirm-password');

        $this->assertDatabaseMissing('user_two_factor_authentications', ['user_id' => $user->id]);
    }

    public function test_setup_creates_an_encrypted_pending_secret_without_enabling_two_factor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post('/pengaturan/keamanan/2fa/setup')
            ->assertRedirect('/pengaturan/keamanan/2fa');

        $security = $user->twoFactorAuthentication()->firstOrFail();
        $pendingSecret = $security->pending_secret;

        $this->assertTrue((bool) preg_match('/^[A-Z2-7]{32}$/', $pendingSecret));
        $this->assertFalse(hash_equals($pendingSecret, $security->getRawOriginal('pending_secret')));
        $this->assertFalse(is_string($security->secret));
        $this->assertNull($security->enabled_at);
        $this->assertTrue($security->pending_expires_at->isFuture());

        $response = $this->get('/pengaturan/keamanan/2fa')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $html = $response->getContent();
        $this->assertTrue(str_contains($html, 'data:image/svg+xml;base64,'));
        $this->assertTrue(str_contains($html, $pendingSecret));
        $this->assertFalse(str_contains($html, 'otpauth://'));
    }

    public function test_invalid_setup_code_does_not_activate_two_factor(): void
    {
        $user = User::factory()->create();
        $this->startSetup($user);
        $security = $user->twoFactorAuthentication()->firstOrFail();
        $invalidCode = $this->guaranteedInvalidTotpCode($security->pending_secret);

        $this->post('/pengaturan/keamanan/2fa/confirm', [
            'code' => $invalidCode,
            'recovery_code_action_token' => $this->recoveryCodeActionToken(),
        ])
            ->assertRedirect('/pengaturan/keamanan/2fa')
            ->assertSessionHasErrors('code');

        $security = $user->twoFactorAuthentication()->firstOrFail();
        $this->assertFalse($security->isEnabled());
        $this->assertFalse(is_string($security->secret));
        $this->assertSame(0, $security->recoveryCodes()->count());
    }

    public function test_abandoned_setup_expires_and_cannot_be_activated(): void
    {
        $user = User::factory()->create();
        $this->startSetup($user);
        $security = $user->twoFactorAuthentication()->firstOrFail();
        $security->forceFill(['pending_expires_at' => now()->subSecond()])->save();
        $code = app(Google2FA::class)->oathTotp($security->pending_secret, intdiv(now()->timestamp, 30));

        $this->post('/pengaturan/keamanan/2fa/confirm', [
            'code' => $code,
            'recovery_code_action_token' => $this->recoveryCodeActionToken(),
        ])
            ->assertRedirect('/pengaturan/keamanan/2fa')
            ->assertSessionHasErrors('code');

        $this->assertFalse($security->fresh()->isEnabled());
        $this->assertSame(0, $security->fresh()->recoveryCodes()->count());
    }

    public function test_valid_setup_code_activates_two_factor_and_shows_recovery_codes_once(): void
    {
        $user = User::factory()->create();
        $this->startSetup($user);
        $security = $user->twoFactorAuthentication()->firstOrFail();
        $secret = $security->pending_secret;
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $verifiedStep = app(Google2FA::class)->verifyKeyNewer(
            $secret,
            $code,
            -1,
            1,
            intdiv(now()->timestamp, 30),
        );

        $this->assertIsInt($verifiedStep);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertTrue($user->fresh()->canMaintainAuthenticatedSession());
        $this->assertFalse($security->isEnabled());
        $this->assertTrue($security->pending_expires_at->isFuture());
        $oldSessionVersion = $user->fresh()->authSessionVersion();
        $oldRememberToken = $user->fresh()->getRememberToken();
        $actionToken = $this->recoveryCodeActionToken();

        $postResponse = $this->post('/pengaturan/keamanan/2fa/confirm', [
            'code' => $code,
            'recovery_code_action_token' => $actionToken,
        ])
            ->assertStatus(303)
            ->assertSessionHas('auth_session_version', 1)
            ->assertHeader('Location', route('security.two-factor.recovery-codes.display'));
        $this->assertNoRecoveryCodes($postResponse);
        $confirmedSessionCookie = $postResponse->getCookie(config('session.cookie'));
        $this->assertNotNull($confirmedSessionCookie);
        $this->withCookie(config('session.cookie'), $confirmedSessionCookie->getValue());
        Auth::forgetGuards();

        $this->assertAuthenticatedAs($user);

        $enabled = $security->fresh();
        $updatedUser = $user->fresh();
        $this->assertSame($oldSessionVersion + 1, $updatedUser->authSessionVersion());
        $this->assertTrue($oldRememberToken !== $updatedUser->getRememberToken());
        $recoveryCodes = $enabled->recoveryCodes()->pluck('code_hash')->all();
        $this->assertCount(10, $recoveryCodes);
        $sessionBinding = session('security.two_factor.recovery_session_binding');
        $this->assertIsString($sessionBinding);
        $this->assertTrue(app(TwoFactorAuthenticationService::class)->confirmSetupForDisplay($user, $code, $actionToken, $sessionBinding));
        $this->assertSame($recoveryCodes, $enabled->fresh()->recoveryCodes()->pluck('code_hash')->all());

        $displayResponse = $this->get('/pengaturan/keamanan/2fa/recovery-codes');
        $this->assertSame(200, $displayResponse->getStatusCode(), 'Recovery display redirect: '.$displayResponse->headers->get('Location'));
        $displayResponse->assertHeader('Cache-Control', 'no-store, private')
            ->assertSeeText(__('settings.two_factor.recovery_codes_heading'));
        preg_match_all('/[A-F0-9]{8}(?:-[A-F0-9]{8}){3}/', $displayResponse->getContent(), $matches);
        $shownCodes = $matches[0];

        $this->assertTrue($enabled->isEnabled());
        $this->assertTrue(hash_equals($secret, $enabled->secret));
        $this->assertFalse(hash_equals($secret, $enabled->getRawOriginal('secret')));
        $this->assertFalse(is_string($enabled->pending_secret));
        $this->assertNull($enabled->pending_expires_at);
        $this->assertCount(10, $shownCodes);
        foreach ($shownCodes as $shownCode) {
            $normalizedCode = str_replace('-', '', $shownCode);
            $this->assertFalse(in_array($normalizedCode, $recoveryCodes, true));
            $this->assertFalse(str_contains(serialize(session()->all()), $normalizedCode));
        }

        $this->assertFalse(str_contains($displayResponse->getContent(), $secret));
        $this->assertNull(parse_url($postResponse->headers->get('Location'), PHP_URL_QUERY));
        $this->assertFalse(session()->has('security.two_factor.recovery_handoff_token'));
        $replayedDisplay = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/pengaturan/keamanan/2fa');
        $this->assertNoRecoveryCodes($replayedDisplay);
        $this->assertSame($recoveryCodes, $enabled->fresh()->recoveryCodes()->pluck('code_hash')->all());
    }

    public function test_wrong_password_never_starts_a_two_factor_challenge(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
            'remember' => '1',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
        $this->assertDatabaseCount('user_two_factor_login_challenges', 0);
        $this->assertFalse($this->hasRecallerCookie($response));
    }

    public function test_suspended_user_with_two_factor_never_reaches_the_challenge(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $user->forceFill([
            'moderation_state' => ModerationState::Suspended->value,
            'moderation_state_version' => 1,
            'auth_session_version' => $user->authSessionVersion() + 1,
        ])->save();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertSessionHasErrors(['email' => trans('moderation.login.suspended')]);

        $this->assertGuest();
        $this->assertDatabaseCount('user_two_factor_login_challenges', 0);
        $this->assertFalse($this->hasRecallerCookie($response));
    }

    public function test_banned_user_with_two_factor_never_reaches_the_challenge(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        $user->forceFill([
            'moderation_state' => ModerationState::Banned->value,
            'moderation_state_version' => 1,
            'auth_session_version' => $user->authSessionVersion() + 1,
        ])->save();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertSessionHasErrors(['email' => trans('moderation.login.banned')]);

        $this->assertGuest();
        $this->assertDatabaseCount('user_two_factor_login_challenges', 0);
        $this->assertFalse($this->hasRecallerCookie($response));
    }

    public function test_valid_password_for_totp_user_starts_a_limited_challenge_without_remembering_them(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('/two-factor/challenge');
        $this->assertGuest();
        $this->assertFalse($this->hasRecallerCookie($response));
        $this->get('/feed')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_totp_completes_the_challenge_and_issues_remember_cookie_only_after_success(): void
    {
        Config::set('session.driver', 'database');

        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect('/two-factor/challenge');
        $this->assertTrue(is_array(session(TwoFactorLoginChallengeService::SESSION_KEY)));

        $this->assertFalse($this->hasRecallerCookie($loginResponse));
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $this->assertTrue($sessionCookie !== null);
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
        Auth::forgetGuards();
        $challengePage = $this->get('/two-factor/challenge')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSeeText(__('auth.two_factor.title'));
        $this->assertGuest();

        $this->travel(31)->seconds();
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $challengeResponse = $this->post('/two-factor/challenge', ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($this->hasRecallerCookie($challengeResponse));
        $this->assertDatabaseHas('user_two_factor_login_challenges', [
            'user_id' => $user->id,
            'consumed_at' => now(),
        ]);
    }

    public function test_restricted_user_remains_read_only_after_totp_login_and_cannot_open_admin(): void
    {
        $user = User::factory()->create(['moderation_state' => ModerationState::Restricted->value]);
        $secret = $this->enableTwoFactor($user);
        $this->authenticateWithTotp($user, $secret);

        $this->get('/feed')->assertOk();
        $adminResponse = $this->get('/admin');
        $this->assertFalse($adminResponse->isOk());
    }

    public function test_verified_two_factor_administrator_can_reach_admin_only_after_the_second_factor(): void
    {
        Config::set('session.driver', 'database');
        $administrator = User::factory()->administrator()->create();
        $secret = $this->enableTwoFactor($administrator);

        $loginResponse = $this->withSession(['url.intended' => '/admin'])
            ->post('/login', [
                'email' => $administrator->email,
                'password' => 'password',
                'remember' => '1',
            ])
            ->assertRedirect('/two-factor/challenge');
        $this->assertGuest();
        $this->assertFalse($this->hasRecallerCookie($loginResponse));

        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $this->assertTrue($sessionCookie !== null);
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
        Auth::forgetGuards();
        $this->travel(31)->seconds();
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $authenticated = $this->post('/two-factor/challenge', ['code' => $code])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($administrator);
        $authenticatedSessionCookie = $authenticated->getCookie(config('session.cookie'));
        $this->assertTrue($authenticatedSessionCookie !== null);
        $this->withCookie(config('session.cookie'), $authenticatedSessionCookie->getValue());
        Auth::forgetGuards();
        $this->get('/admin')->assertOk();
    }

    public function test_recovery_code_completes_login_once_and_only_the_hash_remains_stored(): void
    {
        Config::set('session.driver', 'database');
        $user = User::factory()->create();
        [, $recoveryCodes] = $this->enableTwoFactorWithRecoveryCodes($user);

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor/challenge');
        $sessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $this->assertTrue($sessionCookie !== null);
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
        Auth::forgetGuards();

        $formattedCode = implode('-', str_split($recoveryCodes[0], 8));
        $response = $this->post('/two-factor/challenge', ['code' => $formattedCode])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $storedHashes = $user->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all();
        $this->assertCount(9, $storedHashes);
        $this->assertFalse(in_array($recoveryCodes[0], $storedHashes, true));
        $expectedRemainingHash = hash('sha256', $recoveryCodes[1]);
        $this->assertTrue(in_array($expectedRemainingHash, $storedHashes, true));

        $authenticatedSessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertTrue($authenticatedSessionCookie !== null);
        $this->withCookie(config('session.cookie'), $authenticatedSessionCookie->getValue());
        Auth::forgetGuards();
        $logoutResponse = $this->post('/logout')->assertRedirect('/');
        $loggedOutSessionCookie = $logoutResponse->getCookie(config('session.cookie'));
        $this->assertTrue($loggedOutSessionCookie !== null);
        $this->withCookie(config('session.cookie'), $loggedOutSessionCookie->getValue());
        Auth::forgetGuards();

        $secondLogin = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor/challenge');
        $secondChallengeCookie = $secondLogin->getCookie(config('session.cookie'));
        $this->assertTrue($secondChallengeCookie !== null);
        $this->withCookie(config('session.cookie'), $secondChallengeCookie->getValue());
        Auth::forgetGuards();

        $this->from('/two-factor/challenge')
            ->post('/two-factor/challenge', ['code' => $formattedCode])
            ->assertRedirect('/two-factor/challenge')
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_regeneration_replaces_all_old_hashes_and_shows_new_codes_once(): void
    {
        Config::set('session.driver', 'database');
        $user = User::factory()->create();
        [$secret, $oldCodes] = $this->enableTwoFactorWithRecoveryCodes($user);
        $this->authenticateWithTotp($user, $secret);
        $hashesBeforeUnauthorizedAttempt = $user->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all();
        $this->post('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/confirm-password');
        $this->assertSame(
            $hashesBeforeUnauthorizedAttempt,
            $user->fresh()->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all(),
        );
        $this->confirmRecentPassword('/pengaturan/keamanan/2fa');
        $this->get('/pengaturan/keamanan/2fa')->assertOk();
        $actionToken = $this->recoveryCodeActionToken();
        $oldHashes = $user->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all();

        $response = $this->post('/pengaturan/keamanan/2fa/recovery-codes', [
            'recovery_code_action_token' => $actionToken,
        ])
            ->assertStatus(303)
            ->assertHeader('Location', route('security.two-factor.recovery-codes.display'));
        $this->assertNoRecoveryCodes($response);
        $this->assertAuthenticatedAs($user);

        $newHashes = $user->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all();
        $this->assertCount(10, $newHashes);
        $this->assertNotEquals($oldHashes, $newHashes);
        $this->assertFalse(in_array(hash('sha256', $oldCodes[0]), $newHashes, true));
        $sessionBinding = session('security.two_factor.recovery_session_binding');
        $this->assertIsString($sessionBinding);
        $this->assertTrue(app(TwoFactorAuthenticationService::class)->regenerateRecoveryCodes($user, $actionToken, $sessionBinding));
        $this->assertSame($newHashes, $user->fresh()->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all());

        $handoff = DB::table('user_two_factor_recovery_code_handoffs')->where('user_id', $user->id)->first();
        $this->assertIsObject($handoff);
        $this->assertIsString($handoff->encrypted_payload);
        $displayCodes = json_decode(Crypt::decryptString($handoff->encrypted_payload), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(10, $displayCodes);
        $handoffStorage = serialize($handoff);
        $recoveryCodeStorage = serialize(DB::table('user_two_factor_recovery_codes')->where('user_id', $user->id)->get()->all());
        foreach ($displayCodes as $displayCode) {
            $this->assertFalse(str_contains($handoffStorage, $displayCode));
            $this->assertFalse(str_contains($recoveryCodeStorage, $displayCode));
            $this->assertFalse(in_array($displayCode, $newHashes, true));
        }
        $this->assertNull(parse_url($response->headers->get('Location'), PHP_URL_QUERY));

        $this->post('/pengaturan/keamanan/2fa/recovery-codes', [
            'recovery_code_action_token' => $actionToken,
        ])->assertRedirect('/pengaturan/keamanan/2fa');
        $this->assertSame($newHashes, $user->fresh()->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all());

        $display = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSeeText(__('settings.two_factor.recovery_codes_regenerated'));
        preg_match_all('/[A-F0-9]{8}(?:-[A-F0-9]{8}){3}/', $display->getContent(), $matches);
        $newCodes = array_map(static fn (string $code): string => str_replace('-', '', $code), $matches[0]);
        $this->assertCount(10, $newCodes);
        $this->assertEqualsCanonicalizing($displayCodes, $newCodes);
        $this->assertTrue(in_array(hash('sha256', $newCodes[0]), $newHashes, true));
        $this->assertFalse(str_contains(serialize(DB::table('user_two_factor_recovery_codes')->where('user_id', $user->id)->get()->all()), $newCodes[0]));
        $consumedHandoff = DB::table('user_two_factor_recovery_code_handoffs')->where('user_id', $user->id)->first();
        $this->assertNull($consumedHandoff->encrypted_payload);
        $this->assertNotNull($consumedHandoff->consumed_at);

        $replayedDisplay = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/pengaturan/keamanan/2fa');
        $this->assertNoRecoveryCodes($replayedDisplay);
        $this->assertSame($newHashes, $user->fresh()->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all());
    }

    public function test_direct_recovery_code_display_get_without_handoff_never_generates_or_reveals_codes(): void
    {
        $user = User::factory()->create();
        [$secret] = $this->enableTwoFactorWithRecoveryCodes($user);
        $this->authenticateWithTotp($user, $secret);
        $this->get('/pengaturan/keamanan/2fa')->assertRedirect('/confirm-password');
        $this->confirmRecentPassword('/pengaturan/keamanan/2fa');
        $originalHashes = $user->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all();

        $replayedDisplay = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/pengaturan/keamanan/2fa');
        $this->assertNoRecoveryCodes($replayedDisplay);

        $this->assertSame(
            $originalHashes,
            $user->fresh()->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all(),
        );
    }

    public function test_recovery_code_display_requires_authentication(): void
    {
        $this->get('/pengaturan/keamanan/2fa/recovery-codes')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_recovery_code_display(): void
    {
        $unverified = User::factory()->unverified()->create();

        $response = $this->actingAs($unverified)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get('/pengaturan/keamanan/2fa/recovery-codes');

        $this->assertSame('/verify-email', parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->assertNoRecoveryCodes($response);
    }

    public function test_recovery_code_display_requires_a_current_account_session(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactorWithRecoveryCodes($user);
        $this->actingAs($user)->withSession([
            'auth_session_version' => $user->authSessionVersion(),
            'auth.password_confirmed_at' => now()->timestamp,
        ]);
        $this->get('/pengaturan/keamanan/2fa')->assertOk();
        $this->post('/pengaturan/keamanan/2fa/recovery-codes', [
            'recovery_code_action_token' => $this->recoveryCodeActionToken(),
        ])->assertStatus(303);
        $user->forceFill(['auth_session_version' => $user->authSessionVersion() + 1])->save();

        $staleSessionResponse = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/login');
        $this->assertNoRecoveryCodes($staleSessionResponse);
    }

    public function test_recovery_code_display_rechecks_recent_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactorWithRecoveryCodes($user);
        $this->actingAs($user)->withSession([
            'auth_session_version' => $user->authSessionVersion(),
            'auth.password_confirmed_at' => now()->timestamp,
        ]);
        $this->get('/pengaturan/keamanan/2fa')->assertOk();
        $this->post('/pengaturan/keamanan/2fa/recovery-codes', [
            'recovery_code_action_token' => $this->recoveryCodeActionToken(),
        ])->assertStatus(303);

        $this->travel(301)->seconds();
        $staleConfirmationResponse = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/confirm-password');
        $this->assertNoRecoveryCodes($staleConfirmationResponse);
        $this->travelBack();
        $this->confirmRecentPassword('/pengaturan/keamanan/2fa/recovery-codes');
        $this->actingAs($user);
        $displayResponse = $this->get('/pengaturan/keamanan/2fa/recovery-codes');
        $this->assertSame(200, $displayResponse->getStatusCode(), 'Redirect location: '.$displayResponse->headers->get('Location'));
        $displayResponse->assertSeeText(__('settings.two_factor.recovery_codes_regenerated'));
    }

    public function test_expired_recovery_code_handoff_cannot_be_displayed(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactorWithRecoveryCodes($user);
        $this->actingAs($user)->withSession([
            'auth_session_version' => $user->authSessionVersion(),
            'auth.password_confirmed_at' => now()->timestamp,
        ]);
        $this->get('/pengaturan/keamanan/2fa')->assertOk();
        $actionToken = $this->recoveryCodeActionToken();
        $this->post('/pengaturan/keamanan/2fa/recovery-codes', [
            'recovery_code_action_token' => $actionToken,
        ])->assertStatus(303);
        $hashes = $user->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all();

        $this->travel(11)->minutes();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $expiredResponse = $this->get('/pengaturan/keamanan/2fa/recovery-codes')
            ->assertRedirect('/pengaturan/keamanan/2fa');
        $this->assertNoRecoveryCodes($expiredResponse);

        $handoff = DB::table('user_two_factor_recovery_code_handoffs')->where('user_id', $user->id)->first();
        $this->assertNull($handoff->encrypted_payload);
        $this->assertNotNull($handoff->consumed_at);
        $this->post('/pengaturan/keamanan/2fa/recovery-codes', [
            'recovery_code_action_token' => $actionToken,
        ])->assertRedirect('/pengaturan/keamanan/2fa');
        $this->assertSame($hashes, $user->fresh()->twoFactorAuthentication()->firstOrFail()->recoveryCodes()->pluck('code_hash')->all());
    }

    public function test_disabling_two_factor_requires_a_new_totp_and_revokes_old_sessions_and_recallers(): void
    {
        Config::set('session.driver', 'database');
        $user = User::factory()->create();
        [$secret, $recoveryCodes] = $this->enableTwoFactorWithRecoveryCodes($user);
        $loginResponse = $this->authenticateWithTotp($user, $secret, true);
        $recaller = $this->recallerCookie($loginResponse);
        $this->assertTrue($recaller !== null);

        $oldSessionVersion = $user->fresh()->authSessionVersion();
        $oldRememberToken = $user->fresh()->getRememberToken();
        $this->confirmRecentPassword();
        $this->delete('/pengaturan/keamanan/2fa', ['code' => 'not-a-code'])
            ->assertRedirect('/pengaturan/keamanan/2fa')
            ->assertSessionHasErrors('code');
        $this->assertTrue($user->twoFactorAuthentication()->firstOrFail()->isEnabled());
        $this->travel(31)->seconds();
        $disableCode = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));

        $this->delete('/pengaturan/keamanan/2fa', ['code' => $disableCode])
            ->assertRedirect('/pengaturan/keamanan/2fa')
            ->assertSessionHas('two_factor_status', 'disabled')
            ->assertSessionHas('auth_session_version', $oldSessionVersion + 1);

        $security = $user->twoFactorAuthentication()->firstOrFail();
        $this->assertFalse($security->isEnabled());
        $this->assertFalse(is_string($security->secret));
        $this->assertSame(0, $security->recoveryCodes()->count());
        $this->assertTrue($oldRememberToken !== $user->fresh()->getRememberToken());
        $this->assertAuthenticatedAs($user);

        $this->flushSession();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->get('/dashboard')
            ->assertRedirect('/login');
        $this->assertGuest();
        $this->assertCount(10, $recoveryCodes);
    }

    private function confirmRecentPassword(string $expectedRedirect = '/dashboard'): void
    {
        $response = $this->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect($expectedRedirect);
        $sessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertTrue($sessionCookie !== null);
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
        Auth::forgetGuards();
    }

    private function guaranteedInvalidTotpCode(string $secret): string
    {
        $currentStep = intdiv(now()->timestamp, 30);
        $validCodes = [];

        foreach (range($currentStep - 1, $currentStep + 1) as $step) {
            $validCodes[] = app(Google2FA::class)->oathTotp($secret, $step);
        }

        for ($value = 0; $value <= 999_999; $value++) {
            $candidate = str_pad((string) $value, 6, '0', STR_PAD_LEFT);

            if (! in_array($candidate, $validCodes, true)) {
                return $candidate;
            }
        }

        throw new \LogicException('No invalid TOTP candidate exists.');
    }

    private function pendingRequest(): Request
    {
        $session = new Store(
            's3-test',
            new ArraySessionHandler(10),
            Str::random(40),
        );
        $session->start();
        $request = Request::create('/two-factor/challenge', 'POST');
        $request->setLaravelSession($session);

        return $request;
    }

    private function startLoginChallenge(User $user, bool $remember = false): TestResponse
    {
        Config::set('session.driver', 'database');
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => $remember ? '1' : '0',
        ])->assertRedirect('/two-factor/challenge');
        $sessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertTrue($sessionCookie !== null);
        $this->withCookie(config('session.cookie'), $sessionCookie->getValue());
        Auth::forgetGuards();

        return $response;
    }

    private function authenticateWithTotp(
        User $user,
        string $secret,
        bool $remember = false,
        ?string $intended = null,
    ): TestResponse {
        if ($intended !== null) {
            $this->withSession(['url.intended' => $intended]);
        }

        $this->startLoginChallenge($user, $remember);
        $this->travel(31)->seconds();
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $response = $this->post('/two-factor/challenge', ['code' => $code]);
        $response->assertRedirect($intended ?? '/dashboard');

        $authenticatedSessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertTrue($authenticatedSessionCookie !== null);
        $this->withCookie(config('session.cookie'), $authenticatedSessionCookie->getValue());
        Auth::forgetGuards();

        return $response;
    }

    private function enableTwoFactor(User $user): string
    {
        [$secret] = $this->enableTwoFactorWithRecoveryCodes($user);

        return $secret;
    }

    /** @return array{0: string, 1: list<string>} */
    private function enableTwoFactorWithRecoveryCodes(User $user): array
    {
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $twoFactor->beginSetup($user);
        $secret = $user->twoFactorAuthentication()->firstOrFail()->pending_secret;
        $code = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $recoveryCodes = $twoFactor->confirmSetup($user, $code);
        $this->assertIsArray($recoveryCodes);

        return [$secret, $recoveryCodes];
    }

    private function hasRecallerCookie($response): bool
    {
        $cookieName = auth()->guard('web')->getRecallerName();

        return collect($response->headers->getCookies())
            ->contains(fn ($cookie) => $cookie->getName() === $cookieName);
    }

    private function recallerCookie($response): mixed
    {
        $cookieName = auth()->guard('web')->getRecallerName();

        return collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $cookieName);
    }

    private function assertNoRecoveryCodes(TestResponse $response): void
    {
        preg_match_all('/\b(?:[A-F0-9]{32}|[A-F0-9]{8}(?:-[A-F0-9]{8}){3})\b/', $response->getContent(), $matches);
        $this->assertSame([], $matches[0]);
    }

    private function recoveryCodeActionToken(): string
    {
        $token = session('security.two_factor.recovery_action_token');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));

        return $token;
    }

    private function startSetup(User $user): void
    {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post('/pengaturan/keamanan/2fa/setup')
            ->assertRedirect('/pengaturan/keamanan/2fa');
        $this->get('/pengaturan/keamanan/2fa')->assertOk();
    }
}
