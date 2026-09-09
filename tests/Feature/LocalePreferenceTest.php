<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
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

    public function test_locale_migration_adds_a_nullable_column_without_rewriting_existing_users(): void
    {
        $migration = require database_path(
            'migrations/2026_09_08_000000_add_locale_to_users_table.php'
        );

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'locale'));

        $createdAt = now()->startOfSecond();
        $userId = DB::table('users')->insertGetId([
            'name' => 'Legacy User',
            'username' => 'legacy_user',
            'email' => 'legacy@example.com',
            'password' => 'legacy-password-hash',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'locale'));

        $user = DB::table('users')->find($userId);

        $this->assertNotNull($user);
        $this->assertSame('Legacy User', $user->name);
        $this->assertSame('legacy_user', $user->username);
        $this->assertSame('legacy@example.com', $user->email);
        $this->assertSame('legacy-password-hash', $user->password);
        $this->assertNull($user->locale);

        $column = collect(DB::select('PRAGMA table_info(users)'))
            ->first(fn (object $column): bool => $column->name === 'locale');

        $this->assertNotNull($column);
        $this->assertSame(0, $column->notnull);
        $this->assertNull($column->dflt_value);
    }

    public function test_user_exposes_only_supported_framework_locale_preferences(): void
    {
        $user = new User();

        $this->assertInstanceOf(HasLocalePreference::class, $user);
        $this->assertNotContains('locale', $user->getFillable());

        $user->locale = 'id';
        $this->assertSame('id', $user->preferredLocale());

        $user->fill(['locale' => 'en']);
        $this->assertSame('id', $user->locale);

        $user->locale = 'en';
        $this->assertSame('en', $user->preferredLocale());

        app()->setLocale('en');
        $user->locale = null;
        $this->assertSame('en', $user->preferredLocale());

        app()->setLocale('fr');
        $user->locale = '../../';
        $this->assertSame('id', $user->preferredLocale());
    }

    public function test_first_guest_request_uses_indonesian_default(): void
    {
        $this->flushSession()->withCookies([]);

        $this->assertSame('id', config('localization.default'));

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

    public function test_authenticated_account_locale_overrides_conflicting_session_and_cookie(): void
    {
        $cookie = $this->localeCookieFromSelection('id');
        $this->flushSession();

        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withSession(['locale' => 'id'])
            ->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en"', false);

        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_invalid_authenticated_account_locale_falls_through_without_middleware_rewrite(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en"', false);

        $this->assertSame('en', app()->getLocale());
        $this->assertSame('fr', $user->fresh()->locale);
    }

    public function test_authenticated_locale_resolution_does_not_leak_between_users(): void
    {
        $englishUser = User::factory()->create(['locale' => 'en']);
        $indonesianUser = User::factory()->create(['locale' => 'id']);

        $this->actingAs($englishUser)
            ->get('/dashboard')
            ->assertSee('<html lang="en"', false);

        $this->actingAs($indonesianUser)
            ->get('/dashboard')
            ->assertSee('<html lang="id"', false);

        $this->assertSame('id', app()->getLocale());
    }

    public function test_authenticated_locale_switch_updates_only_the_current_users_preference(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $otherUser = User::factory()->create(['locale' => 'id']);
        $before = $user->fresh()->only([
            'name',
            'username',
            'email',
            'password',
            'avatar',
            'cover',
            'bio',
        ]);

        $response = $this->actingAs($user)
            ->post('/locale', [
                'locale' => 'en',
                'user_id' => $otherUser->id,
            ])
            ->assertRedirect('/');

        $response->assertSessionHas('locale', 'en');
        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('id', $otherUser->fresh()->locale);
        $this->assertSame($before, $user->fresh()->only(array_keys($before)));

        $this->assertLocaleCookieValue($response, 'en');
    }

    public function test_authenticated_locale_switch_can_restore_indonesian(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)
            ->post('/locale', ['locale' => 'id'])
            ->assertRedirect('/');

        $response->assertSessionHas('locale', 'id');
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('id', $user->fresh()->locale);
        $this->assertLocaleCookieValue($response, 'id');
    }

    public function test_invalid_authenticated_locale_switch_does_not_change_the_account(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/locale', ['locale' => 'fr'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale');

        $this->assertSame('id', $user->fresh()->locale);
    }

    public function test_logout_preserves_account_locale_and_the_dedicated_locale_cookie(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $loginResponse = $this->withSession(['locale' => 'id'])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $cookie = $this->localeCookie($loginResponse);

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSame('en', $user->fresh()->locale);

        $this->flushSession();

        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en">', false);
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

    private function assertLocaleCookieValue($response, string $locale): void
    {
        $cookie = $this->localeCookie($response);

        $this->assertSame(
            CookieValuePrefix::create(
                config('localization.cookie_name'),
                app('encrypter')->getKey(),
            ).$locale,
            Crypt::decrypt($cookie->getValue(), false),
        );
    }
}
