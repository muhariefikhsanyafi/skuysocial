<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_stored_english_locale_overrides_stale_guest_preferences_after_login(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this
            ->withSession(['locale' => 'id'])
            ->withUnencryptedCookie(
                config('localization.cookie_name'),
                $this->encryptedLocaleCookie('id'),
            )
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('locale', 'en');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertLocaleCookieValue($response, 'en');
    }

    public function test_stored_indonesian_locale_overrides_stale_guest_preferences_after_login(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $response = $this
            ->withSession(['locale' => 'en'])
            ->withUnencryptedCookie(
                config('localization.cookie_name'),
                $this->encryptedLocaleCookie('en'),
            )
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertSessionHas('locale', 'id');
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('id', $user->fresh()->locale);
        $this->assertLocaleCookieValue($response, 'id');
    }

    public function test_legacy_null_locale_captures_the_effective_session_locale_once(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $firstLogin = $this->withSession(['locale' => 'en'])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $firstLogin->assertSessionHas('locale', 'en');
        $this->assertSame('en', $user->fresh()->locale);

        $this->post('/logout')->assertRedirect('/');
        $this->flushSession();

        $secondLogin = $this->withSession(['locale' => 'id'])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $secondLogin->assertSessionHas('locale', 'en');
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertLocaleCookieValue($secondLogin, 'en');
    }

    public function test_legacy_null_locale_can_be_captured_from_the_guest_cookie(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $response = $this
            ->withUnencryptedCookie(
                config('localization.cookie_name'),
                $this->encryptedLocaleCookie('en'),
            )
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertSessionHas('locale', 'en');
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertLocaleCookieValue($response, 'en');
    }

    public function test_legacy_null_locale_uses_the_product_default_without_guest_preference(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHas('locale', 'id');
        $this->assertSame('id', $user->fresh()->locale);
        $this->assertLocaleCookieValue($response, 'id');
    }

    public function test_successful_login_heals_an_invalid_stored_locale_from_the_safe_effective_locale(): void
    {
        $user = User::factory()->create(['locale' => 'EN']);

        $response = $this->withSession(['locale' => 'en'])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHas('locale', 'en');
        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertLocaleCookieValue($response, 'en');
    }

    public function test_successful_login_still_regenerates_the_session_identifier(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $this->withSession(['locale' => 'en']);
        $sessionIdBeforeLogin = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    private function encryptedLocaleCookie(string $locale): string
    {
        $name = config('localization.cookie_name');

        return Crypt::encryptString(
            CookieValuePrefix::create($name, app('encrypter')->getKey()).$locale
        );
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
