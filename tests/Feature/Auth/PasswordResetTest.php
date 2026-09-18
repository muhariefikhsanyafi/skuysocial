<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertOk()
            ->assertSee('<h1 id="forgot-password-heading" class="skuy-auth-heading">Lupa kata sandi?</h1>', false)
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('href="'.route('login').'"', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_invalid_email_is_associated_with_the_email_field(): void
    {
        $this->followingRedirects()
            ->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="email-error"', false)
            ->assertSee('id="email-error"', false);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_and_registered_email_addresses_receive_the_same_response(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $unknownResponse = $this
            ->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'unknown@example.com']);
        $registeredResponse = $this
            ->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email]);

        foreach ([$unknownResponse, $registeredResponse] as $response) {
            $response
                ->assertRedirect('/forgot-password')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', __(Password::RESET_LINK_SENT));
        }

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/forgot-password', [
                'email' => "unknown-{$attempt}@example.com",
            ])->assertRedirect();
        }

        $this->post('/forgot-password', [
            'email' => 'unknown-6@example.com',
        ])->assertStatus(429);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get(route('password.reset', [
                'token' => $notification->token,
                'email' => 'reset@example.com',
            ]));

            $response
                ->assertOk()
                ->assertSee('<h1 id="reset-password-heading" class="skuy-auth-heading">Atur ulang kata sandi</h1>', false)
                ->assertSee('action="'.route('password.store').'"', false)
                ->assertSee('type="hidden" name="token"', false)
                ->assertSee('name="email"', false)
                ->assertSee('type="email"', false)
                ->assertSee('autocomplete="email"', false)
                ->assertSee('value="reset@example.com"', false)
                ->assertSee('id="password"', false)
                ->assertSee('id="password_confirmation"', false)
                ->assertSee('type="password"', false)
                ->assertSee('autocomplete="new-password"', false)
                ->assertSee('class="skuy-auth-password-toggle"', false)
                ->assertSee('aria-label="Tampilkan kata sandi"', false)
                ->assertSee('data-hide-label="Sembunyikan kata sandi"', false)
                ->assertSee('href="'.route('login').'"', false);

            $this->assertSame(1, substr_count($response->getContent(), '<h1'));
            $this->assertSame(2, substr_count($response->getContent(), 'class="skuy-auth-password-toggle"'));

            return true;
        });
    }

    public function test_password_confirmation_validation_is_associated_with_the_password_field(): void
    {
        $response = $this
            ->from('/reset-password/test-token')
            ->followingRedirects()
            ->post('/reset-password', [
                'token' => 'test-token',
                'email' => 'reset@example.com',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response
            ->assertOk()
            ->assertSee('aria-describedby="password-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="password-error"', false);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
