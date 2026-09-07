<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_localization_configuration_is_deterministic(): void
    {
        $this->assertSame(['id', 'en'], array_keys(config('localization.supported')));
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));
    }

    public function test_first_guest_request_uses_indonesian_default(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="id">', false);

        $this->assertSame('id', app()->getLocale());
    }

    public function test_valid_session_locale_is_used_and_unsupported_session_locale_is_ignored(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/login')
            ->assertSee('<html lang="en">', false);

        $this->flushSession();

        $this->withSession(['locale' => 'id'])
            ->get('/login')
            ->assertSee('<html lang="id">', false);

        $this->flushSession();

        $this->withSession(['locale' => 'fr'])
            ->get('/login')
            ->assertSee('<html lang="id">', false);
    }

    public function test_session_locale_takes_precedence_over_cookie_locale(): void
    {
        $cookie = $this->localeCookieFromSelection('en');

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->withSession(['locale' => 'id'])
            ->get('/login')
            ->assertSee('<html lang="id">', false);
    }

    public function test_valid_encrypted_cookie_restores_locale_without_session_value(): void
    {
        $cookie = $this->localeCookieFromSelection('en');

        $this->flushSession();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/login')
            ->assertSee('<html lang="en">', false);
    }

    public function test_unsupported_encrypted_cookie_is_ignored(): void
    {
        $name = config('localization.cookie_name');
        $key = app('encrypter')->getKey();
        $cookie = Crypt::encryptString(CookieValuePrefix::create($name, $key).'fr');

        $this->withUnencryptedCookie($name, $cookie)
            ->get('/login')
            ->assertSee('<html lang="id">', false);
    }

    public function test_malformed_cookie_is_ignored_without_unsafe_state(): void
    {
        $this->withUnencryptedCookie(config('localization.cookie_name'), 'not-a-valid-encrypted-cookie')
            ->get('/login')
            ->assertSee('<html lang="id">', false);

        $this->assertSame('id', app()->getLocale());
    }

    public function test_supported_locales_can_be_selected_and_cookie_properties_are_safe(): void
    {
        foreach (['id', 'en'] as $locale) {
            $response = $this->post('/locale', [
                'locale' => $locale,
                'return_to' => '/login',
            ]);

            $response->assertRedirect('/login');
            $response->assertSessionHas('locale', $locale);
            $this->assertSame($locale, app()->getLocale());

            $cookie = $this->localeCookie($response);

            $this->assertSame('/', $cookie->getPath());
            $this->assertNull($cookie->getDomain());
            $this->assertTrue($cookie->isHttpOnly());
            $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
            $this->assertSame((bool) (config('session.secure') ?? false), $cookie->isSecure());
            $this->assertNotSame($locale, $cookie->getValue());
            $this->assertSame(
                CookieValuePrefix::create(config('localization.cookie_name'), app('encrypter')->getKey()).$locale,
                Crypt::decrypt($cookie->getValue(), false),
            );

            if (method_exists($cookie, 'isPartitioned')) {
                $this->assertFalse($cookie->isPartitioned());
            }
        }
    }

    public function test_cookie_lifetime_is_one_year(): void
    {
        $response = $this->post('/locale', ['locale' => 'en']);
        $cookie = $this->localeCookie($response);

        $this->assertEqualsWithDelta(
            now()->timestamp + (525600 * 60),
            $cookie->getExpiresTime(),
            5,
        );
    }

    public function test_invalid_locale_input_is_rejected(): void
    {
        $this->from('/login')
            ->post('/locale', ['locale' => 'fr', 'return_to' => '/login'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('locale');

        $this->post('/locale', ['locale' => ['en']])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale');

        $this->post('/locale', ['locale' => str_repeat('x', 101)])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale');
    }

    public function test_get_is_not_a_locale_mutation_endpoint(): void
    {
        $route = app('router')->getRoutes()->getByName('locale.update');

        $this->assertContains('web', $route->middleware());
        $this->get('/locale')->assertStatus(405);
    }

    public function test_only_safe_relative_return_paths_are_accepted(): void
    {
        $this->post('/locale', [
            'locale' => 'en',
            'return_to' => '/search?q=test',
        ])->assertRedirect('/search?q=test');

        foreach ([
            'https://evil.example',
            'http://evil.example',
            '//evil.example',
            'javascript:alert(1)',
            "/login\r\nLocation: https://evil.example",
            str_repeat('/', 2049),
        ] as $returnTo) {
            $this->post('/locale', [
                'locale' => 'en',
                'return_to' => $returnTo,
            ])->assertRedirect('/')
                ->assertSessionHasErrors('return_to');
        }
    }

    public function test_authenticated_locale_switch_does_not_modify_user_account_fields(): void
    {
        $user = User::factory()->create();
        $before = $user->fresh()->getAttributes();

        $this->actingAs($user)
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/');

        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_locale_is_resolved_again_on_each_request_without_state_bleed(): void
    {
        config(['app.locale' => 'id']);

        $this->withSession(['locale' => 'en'])
            ->get('/login')
            ->assertSee('<html lang="en">', false);

        $this->flushSession();

        $this->get('/login')
            ->assertSee('<html lang="id">', false);
    }

    private function localeCookieFromSelection(string $locale): Cookie
    {
        return $this->localeCookie($this->post('/locale', ['locale' => $locale]));
    }

    private function localeCookie($response): Cookie
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('localization.cookie_name'));

        $this->assertInstanceOf(Cookie::class, $cookie);

        return $cookie;
    }
}
