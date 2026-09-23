<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Services\EmailVerificationOtpResult;
use App\Services\EmailVerificationOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('mail.default', 'array');
        Notification::fake();
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response
            ->assertOk()
            ->assertSeeText(__('auth.verify_email.title'))
            ->assertSee('autocomplete="one-time-code"', false);
    }

    public function test_email_can_be_verified_with_a_single_use_code_and_dispatches_the_framework_event(): void
    {
        $user = User::factory()->unverified()->create();
        $this->assertSame(
            EmailVerificationOtpResult::Sent,
            app(EmailVerificationOtpService::class)->issue($user),
        );
        $code = Notification::sent($user, EmailVerificationCode::class)->firstOrFail()->code;
        Event::fake([Verified::class]);

        $response = $this->actingAs($user)->post(route('verification.verify'), ['code' => $code]);

        Event::assertDispatchedTimes(Verified::class, 1);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
        $this->assertDatabaseMissing('email_verification_otps', ['user_id' => $user->id]);
    }

    public function test_legacy_signed_link_does_not_verify_an_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/verify-email/'.$user->id.'/'.sha1('wrong-email').'?signature=legacy&expires=9999999999')
            ->assertNotFound();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
