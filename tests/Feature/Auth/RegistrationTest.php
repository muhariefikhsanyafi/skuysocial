<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
