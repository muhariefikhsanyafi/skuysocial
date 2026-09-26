<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_auth_layout_exposes_the_compact_bilingual_switcher(): void
    {
        foreach ([
            'id' => [
                'html' => '<html lang="id">',
                'language' => 'Bahasa',
                'change' => 'Ganti bahasa',
                'trigger' => 'Pilih bahasa',
                'active' => 'Bahasa saat ini: Bahasa Indonesia',
                'switch_to' => 'Ganti ke English',
                'choice' => 'Indonesia',
            ],
            'en' => [
                'html' => '<html lang="en">',
                'language' => 'Language',
                'change' => 'Change language',
                'trigger' => 'Choose language',
                'active' => 'Current language: English',
                'switch_to' => 'Switch to Bahasa Indonesia',
                'choice' => 'Indonesia',
            ],
        ] as $locale => $copy) {
            $response = $this->withSession(['locale' => $locale])->get('/login');

            $response
                ->assertOk()
                ->assertSee($copy['html'], false)
                ->assertSee('action="'.route('locale.update').'"', false)
                ->assertSee('method="POST"', false)
                ->assertSee('name="_token"', false)
                ->assertSee('name="locale"', false)
                ->assertSee('value="id"', false)
                ->assertSee('value="en"', false)
                ->assertSee('name="return_to" value="/login"', false)
                ->assertSee('aria-haspopup="menu"', false)
                ->assertSee('role="menu"', false)
                ->assertSee('role="menuitem"', false)
                ->assertSee('<circle cx="12" cy="12" r="9"></circle>', false)
                ->assertSee('aria-label="'.$copy['trigger'].'"', false)
                ->assertSee('Bahasa Indonesia', false)
                ->assertSeeText('English')
                ->assertSeeText($copy['choice'])
                ->assertSeeText($copy['language'])
                ->assertSee('aria-label="'.$copy['change'].'"', false)
                ->assertSee('aria-label="'.$copy['active'].'"', false)
                ->assertSee('aria-label="'.$copy['switch_to'].'"', false)
                ->assertSee('aria-pressed="true"', false)
                ->assertSee('aria-current="true"', false);

            $this->assertSame(1, substr_count($response->getContent(), 'aria-pressed="true"'));
            $this->assertSame(1, substr_count($response->getContent(), 'aria-controls="language-menu-'));
            $this->assertStringNotContainsString('>Bahasa Indonesia<', $response->getContent());
        }
    }

    public function test_guest_routes_share_one_compact_switcher_without_a_standalone_block(): void
    {
        foreach (['/login', '/register'] as $path) {
            $response = $this->withSession(['locale' => 'id'])->get($path);

            $response
                ->assertOk()
                ->assertSee('aria-label="Pilih bahasa"', false)
                ->assertSee('aria-haspopup="menu"', false)
                ->assertDontSee('grid gap-4 sm:grid-cols-2', false);

            $this->assertSame(1, substr_count($response->getContent(), 'aria-haspopup="menu"'));
        }
    }

    public function test_language_switcher_heading_tokens_are_scoped_to_the_app_variant(): void
    {
        $defaultHeadingClass = 'skuy-guest-language-menu__heading';
        $appHeadingClass = 'border-b border-[var(--skuy-border-subtle)] px-3 py-2 text-xs font-bold uppercase text-[var(--skuy-text-muted)]';

        foreach (['/', '/login'] as $path) {
            $response = $this->withSession(['locale' => 'id'])->get($path);

            $response
                ->assertOk()
                ->assertSee($defaultHeadingClass, false)
                ->assertDontSee($appHeadingClass, false);
        }

        $appResponse = $this->actingAs(User::factory()->create())->get('/dashboard');

        $appResponse
            ->assertOk()
            ->assertSee($appHeadingClass, false)
            ->assertDontSee($defaultHeadingClass, false);
    }

    public function test_fresh_guest_uses_the_configured_default_without_saved_preference(): void
    {
        $this->flushSession()->withCookies([]);

        $configuredDefault = config('localization.default');

        $this->assertContains($configuredDefault, array_keys(config('localization.supported', [])));

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="'.$configuredDefault.'">', false);

        $this->assertSame($configuredDefault, app()->getLocale());
    }

    public function test_public_home_exposes_the_switcher_and_preserves_root_return_path(): void
    {
        $homeResponse = $this->withSession(['locale' => 'id'])
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('action="'.route('locale.update').'"', false)
            ->assertSee('name="return_to" value="/"', false)
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSeeText('Indonesia')
            ->assertSeeText('English');

        $homeContent = $homeResponse->getContent();
        $this->assertLessThan(
            strpos($homeContent, '</nav>'),
            strpos($homeContent, 'aria-haspopup="menu"'),
        );
        $this->assertStringNotContainsString('grid gap-4 sm:grid-cols-2', $homeContent);

        $this->from('/')
            ->post('/locale', [
                'locale' => 'en',
                'return_to' => '/',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'en');

        $this->get('/')
            ->assertSee('<html lang="en">', false)
            ->assertSee('name="return_to" value="/"', false);
    }

    public function test_guest_can_switch_locale_and_return_to_the_same_local_page(): void
    {
        $this->withSession(['locale' => 'id'])
            ->from('/login?notice=1')
            ->post('/locale', [
                'locale' => 'en',
                'return_to' => '/login?notice=1',
            ])
            ->assertRedirect('/login?notice=1')
            ->assertSessionHas('locale', 'en');

        $this->get('/login?notice=1')
            ->assertSee('<html lang="en">', false)
            ->assertSee('aria-label="Current language: English"', false);

        $this->from('/login?notice=1')
            ->post('/locale', [
                'locale' => 'id',
                'return_to' => '/login?notice=1',
            ])
            ->assertRedirect('/login?notice=1')
            ->assertSessionHas('locale', 'id');

        $this->get('/login?notice=1')
            ->assertSee('<html lang="id">', false)
            ->assertSee('aria-label="Bahasa saat ini: Bahasa Indonesia"', false);
    }

    public function test_each_authenticated_settings_surface_uses_the_same_switcher(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $settingsUrls = [route('profile.edit', $user->username), route('user.edit', $user->username)];

        foreach ($settingsUrls as $settingsUrl) {
            $response = $this->actingAs($user)->get($settingsUrl);

            $response->assertOk();
            $this->assertRootLanguage($response, 'en');

            $response
                ->assertSee('action="'.route('locale.update').'"', false)
                ->assertSee('name="locale"', false)
                ->assertSee('value="id"', false)
                ->assertSee('value="en"', false)
                ->assertSee('aria-label="Current language: English"', false)
                ->assertSee('Switch to Bahasa Indonesia', false)
                ->assertSee('aria-haspopup="menu"', false)
                ->assertDontSee('grid gap-4 sm:grid-cols-2', false)
                ->assertDontSee('name="user_id"', false);

            $this->assertSame(1, substr_count($response->getContent(), 'aria-controls="language-menu-'));
        }
    }

    public function test_authenticated_global_navigation_exposes_the_compact_switcher(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('aria-label="Choose language"', false)
            ->assertSee('aria-haspopup="menu"', false)
            ->assertDontSee('grid gap-4 sm:grid-cols-2', false);

        $this->assertSame(1, substr_count($response->getContent(), 'aria-controls="language-menu-'));
    }

    public function test_authenticated_switch_updates_only_the_current_user_and_preserves_local_return(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $otherUser = User::factory()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->from('/pengaturan/profil')
            ->post('/locale', [
                'locale' => 'en',
                'return_to' => '/pengaturan/profil',
                'user_id' => $otherUser->id,
            ])
            ->assertRedirect('/pengaturan/profil')
            ->assertSessionHas('locale', 'en');

        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('id', $otherUser->fresh()->locale);

        $this->actingAs($user)
            ->from('/profile')
            ->post('/locale', [
                'locale' => 'id',
                'return_to' => '/profile',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('locale', 'id');

        $this->assertSame('id', $user->fresh()->locale);
    }

    public function test_locale_switcher_is_post_only_and_keeps_existing_route_guardrails(): void
    {
        $route = app('router')->getRoutes()->getByName('locale.update');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertContains('web', $route->middleware());

        $this->get('/locale')->assertStatus(405);

        $this->post('/locale', [
            'locale' => 'en',
            'return_to' => 'https://evil.example',
        ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('return_to');
    }

    private function assertRootLanguage($response, string $locale): void
    {
        $this->assertMatchesRegularExpression(
            '/\A<!DOCTYPE html>\s*<html\s+lang="'.preg_quote($locale, '/').'"(?:\s|>)/',
            $response->getContent(),
        );
    }
}
