<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_feed_uses_functional_navigation_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('feed'));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('href="'.route('user.profile', $user->username).'"', false);

        $this->assertSame(2, substr_count($html, 'href="'.route('search').'"'));
        $this->assertSame(2, substr_count($html, 'href="'.route('activity.index').'"'));
        $this->assertStringContainsString('<div class="skuy-app-shell">', $html);
        $this->assertStringContainsString('<div class="skuy-app-shell__workspace">', $html);
        $this->assertSame(2, substr_count($html, 'class="skuy-app-nav__group"'));
        $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
        $this->assertStringContainsString(':aria-expanded="sidebarOpen"', $html);
        $this->assertStringContainsString(":aria-label=\"sidebarOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi'\"", $html);
        $this->assertStringContainsString('id="mobile-navigation"', $html);
        $this->assertStringContainsString('aria-controls="mobile-profile-menu"', $html);
        $this->assertStringContainsString(':aria-expanded="open"', $html);
        $this->assertStringContainsString(":aria-label=\"open ? 'Tutup menu profil' : 'Buka menu profil'\"", $html);
        $this->assertStringContainsString('id="desktop-profile-trigger"', $html);
        $this->assertStringContainsString('aria-controls="desktop-profile-menu"', $html);
        $this->assertStringContainsString('id="desktop-profile-menu"', $html);
        $this->assertStringContainsString('aria-labelledby="desktop-profile-trigger"', $html);
        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        $this->assertStringContainsString('@keydown.escape.window="if (open) closeMenu(true)"', $html);
        $this->assertStringContainsString('aria-label="Cari"', $html);
        $this->assertStringContainsString('aria-label="Aktivitas"', $html);
        $this->assertStringContainsString('aria-label="Profil"', $html);
        $this->assertStringContainsString('skuy-profile-icon', $html);
        $this->assertStringContainsString('aria-label="Buat utas"', $html);
        $this->assertStringContainsString(':aria-pressed="darkMode"', $html);
        $this->assertStringContainsString(":aria-label=\"darkMode ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'\"", $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('feed'), '/').'"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="'.preg_quote(route('search'), '/').'"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="'.preg_quote(route('activity.index'), '/').'"[^>]*aria-current="page"/', $html);

        $bottomNavStart = strpos($html, '<nav class="skuy-mobile-nav"');
        $this->assertNotFalse($bottomNavStart);
        $this->assertStringNotContainsString(route('bookmarks.index'), substr($html, $bottomNavStart));

        $mobileNavigationStart = strpos($html, 'id="mobile-navigation"');
        $this->assertNotFalse($mobileNavigationStart);
        $this->assertStringContainsString(route('bookmarks.index'), substr($html, $mobileNavigationStart));

        $desktopDropdownStart = strpos($html, 'class="skuy-profile-trigger"');
        $mobileProfileStart = strpos($html, 'id="mobile-profile-menu"');
        $this->assertNotFalse($desktopDropdownStart);
        $this->assertNotFalse($mobileProfileStart);
        $this->assertStringNotContainsString(route('bookmarks.index'), substr($html, $desktopDropdownStart, $mobileProfileStart - $desktopDropdownStart));
    }

    public function test_create_thread_rail_cta_has_theme_safe_hover_pressed_and_focus_states(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('feed'));
        $css = file_get_contents(resource_path('css/app.css'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('feed').'#composer" class="skuy-app-rail__compose"', false)
            ->assertSeeText(__('navigation.create_thread'));
        $this->assertIsString($css);

        preg_match_all('/\.skuy-app-rail__compose(?::(?:hover|active|focus-visible))?\s*\{[^}]*\}/', $css, $ctaRules);
        $rules = implode("\n", $ctaRules[0]);

        $this->assertStringContainsString('background-color: var(--skuy-brand);', $rules);
        $this->assertStringContainsString('background-color: var(--skuy-brand-hover);', $rules);
        $this->assertStringContainsString('background-color: var(--skuy-brand-pressed);', $rules);
        $this->assertStringContainsString('color: var(--skuy-on-brand);', $rules);
        $this->assertStringContainsString('-webkit-tap-highlight-color: transparent;', $rules);
        $this->assertStringContainsString('transform: translateY(1px);', $rules);
        $this->assertStringContainsString('transition: background-color 160ms ease', $rules);
        $this->assertStringNotContainsString('background: linear-gradient(', $rules);
        $this->assertStringNotContainsString('background: var(--skuy-brand-hover);', $rules);
        $this->assertStringNotContainsString('background:', str_replace('background-color:', '', $rules));
        $this->assertStringNotContainsString('background-image', $rules);
        $this->assertDoesNotMatchRegularExpression('/background(?:-color)?:\s*(?:black|#000(?:000)?|rgb\(0\s*,\s*0\s*,\s*0)/i', $rules);
        $this->assertStringNotContainsString('.skuy-app-rail__compose::before', $css);
        $this->assertStringNotContainsString('.skuy-app-rail__compose::after', $css);
        $this->assertStringContainsString('body.skuy-product :where(a, button, input, textarea, select):focus-visible', $css);
    }

    public function test_authenticated_topic_uses_functional_navigation_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('topik.show', 'navigation-topic'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('search').'"', false)
            ->assertSee('href="'.route('activity.index').'"', false)
            ->assertSee(route('feed'), false);
    }

    public function test_dashboard_is_an_authenticated_home_base_with_real_destinations(): void
    {
        $user = User::factory()->create([
            'name' => 'Dashboard Reader',
            'username' => 'dashboard_reader',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $html = $response->getContent();
        $headingCount = preg_match_all('/<h1\b/i', $html, $headings);

        $response
            ->assertOk()
            ->assertSee('class="skuy-dashboard-frame skuy-dashboard-frame--bento"', false)
            ->assertSeeText(__('home.dashboard.greeting', ['name' => $user->name]))
            ->assertSee('href="'.route('feed').'#composer"', false)
            ->assertSee('href="'.route('user.profile', $user->username).'"', false)
            ->assertSee('href="'.route('search').'"', false)
            ->assertSee('href="'.route('activity.index').'"', false)
            ->assertSee('href="'.route('bookmarks.index').'"', false)
            ->assertSee('class="skuy-dashboard-route skuy-dashboard-route--feed"', false)
            ->assertSee('class="skuy-dashboard-route skuy-dashboard-route--search"', false)
            ->assertSee('class="skuy-dashboard-route skuy-dashboard-route--activity"', false)
            ->assertSee('class="skuy-dashboard-route skuy-dashboard-route--saved"', false)
            ->assertSee('id="dashboard-title"', false)
            ->assertSee('aria-current="page"', false);

        $this->assertSame(1, $headingCount);

        $heroPosition = strpos($html, 'class="skuy-dashboard-welcome"');
        $accountPosition = strpos($html, 'class="skuy-dashboard-account"');
        $shortcutsPosition = strpos($html, 'class="skuy-dashboard-routes"');
        $this->assertNotFalse($heroPosition);
        $this->assertNotFalse($accountPosition);
        $this->assertNotFalse($shortcutsPosition);
        $this->assertLessThan($accountPosition, $heroPosition);
        $this->assertLessThan($shortcutsPosition, $accountPosition);
    }

    public function test_dashboard_composition_uses_compact_modules_and_an_asymmetric_wide_bento(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        foreach ([
            'min-height: clamp(288px, 21vw, 344px);',
            'justify-content: flex-start;',
            'align-self: flex-start;',
            'gap: clamp(12px, 1.2vw, 18px);',
            '.skuy-dashboard-route--feed {',
            '.skuy-dashboard-route--saved {',
            '"feed feed feed feed feed feed search search search saved saved saved"',
            '"feed feed feed feed feed feed activity activity activity saved saved saved";',
        ] as $dashboardRule) {
            $this->assertStringContainsString($dashboardRule, $css);
        }

        $this->assertStringNotContainsString('min-height: clamp(360px, 33vw, 480px);', $css);
    }

    public function test_authenticated_shell_uses_semantic_surfaces_and_adaptive_desktop_rails(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        foreach ([
            '--skuy-bg-canvas: #f3f0ea;',
            '--skuy-bg-workspace: #faf8f4;',
            '--skuy-bg-canvas: #0b0f12;',
            '--skuy-bg-workspace: #12191d;',
            '--skuy-surface-base:',
            '--skuy-surface-raised:',
            '--skuy-surface-overlay:',
            '--skuy-surface-glass: rgb(255 254 250 / 0.92);',
            '--skuy-surface-glass: rgb(17 24 28 / 0.92);',
            '--skuy-surface-accent: var(--skuy-selected);',
            '--skuy-border-soft:',
            '--skuy-shadow-soft:',
            '--skuy-shadow-raised: var(--skuy-elevation-2);',
            '--skuy-shadow-floating:',
            '--skuy-glow-primary: rgb(13 107 101 / 0.12);',
            '--skuy-glow-secondary: rgb(101 176 160 / 0.08);',
            '--skuy-shell-nav-width: 250px;',
            'background-image:',
            'backdrop-filter: blur(8px);',
            'backdrop-filter: blur(4px);',
            'backdrop-filter: blur(6px);',
            '@media (prefers-reduced-transparency: reduce)',
            '.skuy-app-nav__group + .skuy-app-nav__group',
            '.skuy-dashboard-frame--bento',
            '"routes routes";',
            '"feed feed feed feed feed feed search search search saved saved saved"',
            '"feed feed feed feed feed feed activity activity activity saved saved saved";',
            '@media (max-width: 1279px)',
            '@media (min-width: 768px) and (max-width: 1023px)',
            'box-shadow: var(--skuy-shadow-floating);',
            '.skuy-dashboard-welcome {',
            '.skuy-dashboard-route {',
            '.skuy-post:hover',
            '.skuy-post__repost',
            '.skuy-context-card:first-child',
            '.skuy-security-shell',
            'grid-template-columns: minmax(0, 1fr) minmax(260px, 300px);',
            '@media (min-width: 1500px)',
        ] as $shellRule) {
            $this->assertStringContainsString($shellRule, $css);
        }
    }

    public function test_unverified_users_are_redirected_to_the_email_verification_challenge(): void
    {
        foreach ([
            'id' => 'Untuk melanjutkan ke SkuySocial, minta kode 6 digit',
            'en' => 'To continue to SkuySocial, request a 6-digit code',
        ] as $locale => $copy) {
            $user = User::factory()->unverified()->create(['locale' => $locale]);

            $this->actingAs($user)
                ->withSession(['locale' => $locale === 'id' ? 'en' : 'id'])
                ->get(route('dashboard'))
                ->assertRedirect(route('verification.notice'));

            $challenge = $this->get(route('verification.notice'));
            $challenge
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'">', false)
                ->assertSeeText($copy);

            $this->get(route('feed'))
                ->assertRedirect(route('verification.notice'));
        }
    }
}
