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

        $response->assertStatus(200);
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
                ->assertSessionHas('status', __(Password::RESET_LINK_SENT, [], 'id'));
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
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
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
