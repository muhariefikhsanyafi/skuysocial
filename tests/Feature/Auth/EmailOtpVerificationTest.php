<?php

namespace Tests\Feature\Auth;

use App\Enums\ModerationState;
use App\Http\Middleware\EnforceAccountSession;
use App\Models\EmailVerificationOtp;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Services\EmailVerificationOtpCodeGenerator;
use App\Services\EmailVerificationOtpResult;
use App\Services\EmailVerificationOtpService;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class EmailOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.turnstile.enabled', false);
        Config::set('mail.default', 'array');
        Notification::fake();
    }

    public function test_registration_sends_one_six_digit_code_stores_only_a_keyed_digest_and_redirects_to_challenge(): void
    {
        $response = $this->post(route('register'), $this->registrationData());
        $user = User::query()->where('email', 'otp-registration@example.com')->firstOrFail();
        $otp = EmailVerificationOtp::query()->where('user_id', $user->id)->firstOrFail();
        $notification = Notification::sent($user, EmailVerificationCode::class)->first();

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'sent');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertInstanceOf(EmailVerificationCode::class, $notification);
        $this->assertSame(1, Notification::sent($user, EmailVerificationCode::class)->count());
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $notification->code);
        $this->assertSame($this->digest($user, $notification->code), $otp->code_digest);
        $this->assertNotSame($notification->code, $otp->code_digest);
        $this->assertEquals(600, $otp->sent_at->diffInSeconds($otp->expires_at));
        $this->assertSame(0, $otp->attempt_count);

        $challenge = $this->get(route('verification.notice'));
        $maskedEmail = substr($user->email, 0, 1).'***@'.substr(strrchr($user->email, '@'), 1);

        $challenge
            ->assertOk()
            ->assertSeeText($maskedEmail)
            ->assertSee('name="code"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertDontSee($user->email)
            ->assertDontSee($notification->code);
    }

    public function test_logging_mailers_and_failover_chains_are_refused_without_sending_or_persisting_codes(): void
    {
        $users = [
            User::factory()->unverified()->create(['email' => 'log-mailer@example.com']),
            User::factory()->unverified()->create(['email' => 'failover-mailer@example.com']),
        ];
        $mailers = Config::get('mail.mailers');
        $mailers['otp-safe-test'] = ['transport' => 'smtp'];
        $mailers['otp-log-test'] = ['transport' => 'log'];
        $mailers['otp-failover-test'] = [
            'transport' => 'failover',
            'mailers' => ['otp-safe-test', 'otp-log-test'],
        ];
        Config::set('mail.mailers', $mailers);

        Log::shouldReceive('warning')
            ->twice()
            ->with('Email verification code delivery failed.', Mockery::on(
                fn (array $context): bool => isset($context['user_id'])
                    && $context['exception'] === LogicException::class
                    && ! array_key_exists('code', $context)
                    && ! array_key_exists('email', $context),
            ));

        Config::set('mail.default', 'otp-log-test');
        $logResult = app(EmailVerificationOtpService::class)->issue($users[0]);
        Config::set('mail.default', 'otp-failover-test');
        $failoverResult = app(EmailVerificationOtpService::class)->issue($users[1]);

        $this->assertSame(EmailVerificationOtpResult::DeliveryFailed, $logResult);
        $this->assertSame(EmailVerificationOtpResult::DeliveryFailed, $failoverResult);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $users[0]->id]);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $users[1]->id]);
        Notification::assertNothingSentTo($users[0]);
        Notification::assertNothingSentTo($users[1]);
    }

    public function test_successful_resend_rerolls_a_duplicate_code_before_sending(): void
    {
        $user = User::factory()->unverified()->create();
        $generator = Mockery::mock(EmailVerificationOtpCodeGenerator::class);
        $generator->shouldReceive('generate')->times(3)->andReturn('123456', '123456', '654321');
        $this->app->instance(EmailVerificationOtpCodeGenerator::class, $generator);
        $service = app(EmailVerificationOtpService::class);

        $this->assertSame(EmailVerificationOtpResult::Sent, $service->issue($user));
        $firstCode = Notification::sent($user, EmailVerificationCode::class)->first()->code;

        $this->travel(60)->seconds();

        $this->assertSame(EmailVerificationOtpResult::Sent, $service->issue($user));
        $codes = Notification::sent($user, EmailVerificationCode::class);

        $this->assertSame(2, $codes->count());
        $this->assertNotSame($firstCode, $codes->last()->code);
        $this->assertSame($this->digest($user, $codes->last()->code), $this->otp($user)->code_digest);
    }

    public function test_registration_mail_failure_leaves_the_account_unverified_and_recoverable_without_leaking_provider_details(): void
    {
        Notification::shouldReceive('sendNow')
            ->once()
            ->with(Mockery::type(User::class), Mockery::type(EmailVerificationCode::class))
            ->andThrow(new \RuntimeException('SMTP provider diagnostic that must not be exposed'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Email verification code delivery failed.', Mockery::on(
                fn (array $context): bool => isset($context['user_id'], $context['exception'])
                    && ! array_key_exists('code', $context)
                    && ! array_key_exists('email', $context),
            ));

        $response = $this->post(route('register'), $this->registrationData());
        $user = User::query()->where('email', 'otp-registration@example.com')->firstOrFail();

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'mail_failed');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSeeText(__('auth.verify_email.mail_failed'))
            ->assertDontSeeText('SMTP provider diagnostic');

        Notification::fake();
        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'sent');
        Notification::assertSentTo($user, EmailVerificationCode::class);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_failed_resend_preserves_the_previously_delivered_code_and_attempt_budget(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $wrongCode = $code === '999999' ? '000000' : '999999';
        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), ['code' => $wrongCode]);
        $original = $this->otp($user);
        $originalDigest = $original->code_digest;
        $originalSentAt = $original->sent_at;

        $this->travel(60)->seconds();
        Notification::shouldReceive('sendNow')
            ->once()
            ->andThrow(new \RuntimeException('private mail transport detail'));
        Log::shouldReceive('warning')->once();

        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertSessionHas('verification_status', 'mail_failed');

        $stillActive = $this->otp($user);
        $this->assertSame($originalDigest, $stillActive->code_digest);
        $this->assertSame($original->attempt_count, $stillActive->attempt_count);
        $this->assertEquals($originalSentAt, $stillActive->sent_at);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_current_code_verifies_canonically_and_is_single_use(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        Event::fake([Verified::class]);
        $this->withSession(['url.intended' => route('feed')]);

        $response = $this->actingAs($user)->post(route('verification.verify'), ['code' => $code]);

        $response->assertRedirect(route('feed'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
        Event::assertDispatchedTimes(Verified::class, 1);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_wrong_expired_and_email_mismatched_codes_are_rejected_without_reflecting_or_flashing_the_code(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $wrongCode = $code === '000000' ? '999999' : '000000';

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), ['code' => $wrongCode])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'invalid');
        $this->assertFalse(session()->has('_old_input.code'));
        $this->assertSame(1, $this->otp($user)->attempt_count);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get(route('verification.notice'))
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="code-hint code-error"', false)
            ->assertSeeText(__('auth.verify_email.invalid'));

        $this->post(route('verification.verify'), ['code' => '12x45'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'invalid');
        $this->assertSame(2, $this->otp($user)->attempt_count);

        $user->forceFill(['email' => 'changed-address@example.com'])->save();
        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'invalid');
        $this->assertSame(3, $this->otp($user)->attempt_count);
        $this->assertNull($user->fresh()->email_verified_at);

        $expiredUser = User::factory()->unverified()->create();
        $expiredCode = $this->issueCode($expiredUser);
        $this->travel(10)->minutes();
        $this->actingAs($expiredUser)
            ->from(route('verification.notice'))
            ->post(route('verification.verify'), ['code' => $expiredCode])
            ->assertSessionHas('verification_status', 'expired');
        $this->assertNull($expiredUser->fresh()->email_verified_at);
    }

    public function test_six_digit_code_with_leading_zero_is_valid(): void
    {
        $user = User::factory()->unverified()->create();
        $code = '004207';
        $now = now();

        EmailVerificationOtp::query()->forceCreate([
            'user_id' => $user->id,
            'code_digest' => $this->digest($user, $code),
            'attempt_count' => 0,
            'expires_at' => $now->copy()->addMinutes(10),
            'sent_at' => $now->copy()->subSeconds(60),
        ]);

        $this->actingAs($user)
            ->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_five_invalid_attempts_exhaust_the_code_and_a_cooldown_eligible_resend_resets_attempts(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);
        $wrongCode = $code === '999999' ? '000000' : '999999';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($user)
                ->from(route('verification.notice'))
                ->post(route('verification.verify'), ['code' => $wrongCode])
                ->assertRedirect(route('verification.notice'));
        }

        $this->assertSame(5, $this->otp($user)->attempt_count);
        $this->post(route('verification.verify'), ['code' => $code])
            ->assertSessionHas('verification_status', 'attempt_limit');
        $this->assertNull($user->fresh()->email_verified_at);

        $this->travel(60)->seconds();
        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'sent');

        $this->assertSame(0, $this->otp($user)->attempt_count);
        $this->assertSame(2, Notification::sent($user, EmailVerificationCode::class)->count());
        $freshCode = $this->sentCode($user, 1);
        $this->post(route('verification.verify'), ['code' => $freshCode])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_resend_cooldown_does_not_rotate_the_current_code_but_successful_resend_invalidates_it(): void
    {
        $user = User::factory()->unverified()->create();
        $oldCode = $this->issueCode($user);
        $oldDigest = $this->otp($user)->code_digest;

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'cooldown');
        $this->assertSame($oldDigest, $this->otp($user)->code_digest);
        $this->assertSame(1, Notification::sent($user, EmailVerificationCode::class)->count());
        $this->assertSame(60, app(EmailVerificationOtpService::class)->cooldownSeconds($user));

        $this->travel(59)->seconds();
        $this->assertSame(1, app(EmailVerificationOtpService::class)->cooldownSeconds($user));
        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertSessionHas('verification_status', 'cooldown');
        $this->assertSame($oldDigest, $this->otp($user)->code_digest);

        $this->travel(1)->seconds();
        $this->assertSame(0, app(EmailVerificationOtpService::class)->cooldownSeconds($user));
        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertSessionHas('verification_status', 'sent');
        $newCode = $this->sentCode($user, 1);
        $this->assertNotSame($oldDigest, $this->otp($user)->code_digest);

        $this->post(route('verification.verify'), ['code' => $oldCode])
            ->assertSessionHas('verification_status', 'invalid');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->post(route('verification.verify'), ['code' => $newCode])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_suspended_and_banned_unverified_users_cannot_reach_the_otp_challenge_through_login(): void
    {
        foreach ([
            [ModerationState::Suspended, trans('moderation.login.suspended')],
            [ModerationState::Banned, trans('moderation.login.banned')],
        ] as [$state, $message]) {
            $user = User::factory()->unverified()->create(['moderation_state' => $state->value]);

            $this->from(route('login'))
                ->post(route('login'), [
                    'email' => $user->email,
                    'password' => 'password',
                    'remember' => '1',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['email' => $message]);

            $this->assertGuest();
            $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
            Notification::assertNothingSentTo($user);
        }
    }

    public function test_unverified_login_is_sent_to_challenge_without_remember_cookie_or_automatic_resend(): void
    {
        $user = User::factory()->unverified()->create();
        $rememberToken = $user->getRememberToken();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($this->cookie($response, Auth::guard('web')->getRecallerName()));
        $this->assertSame($rememberToken, $user->fresh()->getRememberToken());
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
        Notification::assertNothingSentTo($user);

        $this->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification_status', 'sent');
        Notification::assertSentTo($user, EmailVerificationCode::class);
    }

    public function test_unverified_users_are_gated_from_product_surfaces_and_social_writes_but_can_verify_and_log_out(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $this->issueCode($user);

        foreach ([route('dashboard'), route('feed'), route('search'), route('profile.edit', $user->username)] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect(route('verification.notice'));
        }

        $this->actingAs($user)
            ->post(route('posts.store'), ['content' => 'Unverified users cannot publish.'])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseMissing('posts', ['user_id' => $user->id]);

        $this->get(route('verification.notice'))->assertOk();
        $this->withSession(['url.intended' => route('feed')])
            ->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('feed'));
        $this->get(route('feed'))->assertOk();

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_unverified_user_can_log_out_from_the_verification_challenge(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk();
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_restricted_unverified_user_is_gated_then_remains_read_only_after_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'moderation_state' => ModerationState::Restricted->value,
        ]);
        $login = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $login->assertRedirect(route('verification.notice'));

        $code = $this->issueCode($user);
        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->get(route('feed'))->assertOk();
        $this->post(route('posts.store'), ['content' => 'Restricted writes remain blocked.'])
            ->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_unverified_remembered_sessions_are_trapped_and_the_pre_verification_recaller_is_rotated_on_success(): void
    {
        $user = User::factory()->create();
        $login = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);
        $cookie = $this->cookie($login, Auth::guard('web')->getRecallerName());
        $this->assertInstanceOf(Cookie::class, $cookie);

        // Model the existing email-change behavior: the account becomes unverified, while an old recaller remains.
        $user->forceFill(['email_verified_at' => null])->save();
        $this->flushSession();
        Auth::forgetGuards();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get(route('feed'))
            ->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard('web')->viaRemember());

        $code = $this->issueCode($user);
        $oldRememberToken = $user->fresh()->getRememberToken();
        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('feed'));
        $this->assertNotSame($oldRememberToken, $user->fresh()->getRememberToken());

        $this->flushSession();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get(route('feed'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_verification_and_resend_routes_have_additional_request_throttles(): void
    {
        $resendUser = User::factory()->unverified()->create();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($resendUser)
                ->from(route('verification.notice'))
                ->post(route('verification.send'))
                ->assertRedirect(route('verification.notice'));
        }

        $this->post(route('verification.send'))->assertTooManyRequests();
        $this->assertSame(1, Notification::sent($resendUser, EmailVerificationCode::class)->count());

        $verificationUser = User::factory()->unverified()->create();
        $code = $this->issueCode($verificationUser);
        $wrongCode = $code === '888888' ? '777777' : '888888';

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($verificationUser)
                ->from(route('verification.notice'))
                ->post(route('verification.verify'), ['code' => $wrongCode])
                ->assertRedirect(route('verification.notice'));
        }

        $this->post(route('verification.verify'), ['code' => $wrongCode])->assertTooManyRequests();
        $this->assertSame(5, $this->otp($verificationUser)->attempt_count);
    }

    public function test_legacy_signed_email_link_cannot_verify_an_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/verify-email/'.$user->id.'/'.sha1($user->email).'?signature=legacy&expires=9999999999')
            ->assertNotFound();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_challenge_copy_is_localized_and_masked_email_is_not_exposed(): void
    {
        foreach ([
            'id' => ['title' => 'Verifikasi alamat email', 'code' => 'Kode 6 digit', 'resend' => 'Kirim kode baru'],
            'en' => ['title' => 'Verify your email address', 'code' => '6-digit code', 'resend' => 'Send a new code'],
        ] as $locale => $copy) {
            $user = User::factory()->unverified()->create([
                'locale' => $locale,
                'email' => $locale.'-long-private-address@example.com',
            ]);
            $this->actingAs($user)
                ->withSession(['locale' => $locale])
                ->get(route('verification.notice'))
                ->assertOk()
                ->assertSeeText($copy['title'])
                ->assertSeeText($copy['code'])
                ->assertSeeText($copy['resend'])
                ->assertDontSee($user->email);
        }
    }

    public function test_email_otp_verification_precedes_totp_and_revokes_the_password_only_session(): void
    {
        Config::set('session.driver', 'database');
        $user = User::factory()->create();
        $twoFactor = app(TwoFactorAuthenticationService::class);
        $twoFactor->beginSetup($user);
        $secret = $user->twoFactorAuthentication()->firstOrFail()->pending_secret;
        $totp = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $this->assertTrue(is_array($twoFactor->confirmSetup($user, $totp)));

        $user->forceFill(['email_verified_at' => null])->save();
        $emailCode = $this->issueCode($user);
        $versionBeforeVerification = $user->fresh()->authSessionVersion();
        $loginResponse = $this->withSession(['url.intended' => '/feed'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'remember' => '1',
            ])
            ->assertRedirect(route('verification.notice'));

        $this->assertFalse($this->hasRememberCookie($loginResponse));
        $this->assertDatabaseCount('user_two_factor_login_challenges', 0);
        $oldSessionCookie = $loginResponse->getCookie(config('session.cookie'));
        $this->assertTrue($oldSessionCookie !== null);
        $parallelSession = new Store(
            config('session.cookie'),
            app('session')->driver('database')->getHandler(),
            Str::random(40),
        );
        $parallelSession->start();
        $parallelSession->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
        $parallelSession->put(EnforceAccountSession::SESSION_VERSION_KEY, $versionBeforeVerification);
        $parallelSession->save();
        $parallelSessionId = $parallelSession->getId();
        $this->withCookie(config('session.cookie'), $oldSessionCookie->getValue());
        Auth::forgetGuards();

        $verificationResponse = $this->post(route('verification.verify'), ['code' => $emailCode])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertFalse($this->hasRememberCookie($verificationResponse));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame($versionBeforeVerification + 1, $user->fresh()->authSessionVersion());
        $this->assertDatabaseHas('user_two_factor_login_challenges', [
            'user_id' => $user->id,
            'remember' => false,
            'intended_url' => '/feed',
            'source' => 'web',
        ]);

        $challengeCookie = $verificationResponse->getCookie(config('session.cookie'));
        $this->assertTrue($challengeCookie !== null);
        foreach ([$oldSessionCookie->getValue(), $parallelSessionId] as $oldSessionId) {
            $this->withCookie(config('session.cookie'), $oldSessionId);
            Auth::forgetGuards();
            $this->get('/feed')->assertRedirect('/login');
            $this->assertGuest();
        }

        $this->withCookie(config('session.cookie'), $challengeCookie->getValue());
        Auth::forgetGuards();
        $this->travel(31)->seconds();
        $currentCode = app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
        $completeResponse = $this->post('/two-factor/challenge', ['code' => $currentCode])
            ->assertRedirect('/feed');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($this->hasRememberCookie($completeResponse));
    }

    private function hasRememberCookie($response): bool
    {
        return $this->cookie($response, Auth::guard('web')->getRecallerName()) !== null;
    }

    private function issueCode(User $user): string
    {
        $this->assertSame(
            EmailVerificationOtpResult::Sent,
            app(EmailVerificationOtpService::class)->issue($user),
        );

        return $this->sentCode($user);
    }

    private function sentCode(User $user, int $index = 0): string
    {
        $notification = Notification::sent($user, EmailVerificationCode::class)->values()->get($index);
        $this->assertInstanceOf(EmailVerificationCode::class, $notification);

        return $notification->code;
    }

    private function otp(User $user): EmailVerificationOtp
    {
        return EmailVerificationOtp::query()->where('user_id', $user->id)->firstOrFail();
    }

    private function digest(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id."\0".$user->email."\0".$code, (string) config('app.key'));
    }

    /**
     * @return array{name: string, username: string, email: string, password: string, password_confirmation: string}
     */
    private function registrationData(): array
    {
        return [
            'name' => 'OTP Registration',
            'username' => 'otp_registration',
            'email' => 'otp-registration@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    private function cookie($response, string $name): ?Cookie
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === $name);

        return $cookie instanceof Cookie ? $cookie : null;
    }
}
