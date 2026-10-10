<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
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
        $xpath = $this->xpath($html);

        $response
            ->assertOk()
            ->assertSee('href="'.route('user.profile', $user->username).'"', false);

        $this->assertSame(2, substr_count($html, 'href="'.route('search').'"'));
        $this->assertSame(2, substr_count($html, 'href="'.route('activity.index').'"'));
        $this->assertSame(1, $xpath->query('//*[@class="skuy-app-shell" and @data-skuy-arah-b-pages="feed"]')->length);
        $feedNavigationLabel = $xpath->query('//nav[@data-navigation-kind="responsive"]//*[@data-nav-key="feed"]//span[contains(@class, "skuy-app-nav__label")]')->item(0);
        $this->assertNotNull($feedNavigationLabel);
        $this->assertSame(__('navigation.home'), trim($feedNavigationLabel->textContent));
        $this->assertStringContainsString('<div class="skuy-app-shell__workspace">', $html);
        $this->assertStringContainsString('aria-controls="mobile-navigation"', $html);
        $this->assertStringContainsString(':aria-expanded="(sidebarOpen && !isDesktop).toString()"', $html);
        $this->assertStringContainsString(":aria-label=\"sidebarOpen && !isDesktop ? 'Tutup menu navigasi' : 'Buka menu navigasi'\"", $html);
        $this->assertStringContainsString('id="mobile-navigation"', $html);
        $this->assertStringContainsString('aria-controls="mobile-profile-menu"', $html);
        $this->assertStringContainsString(':aria-expanded="accountMenuOpen.toString()"', $html);
        $this->assertStringContainsString(":aria-label=\"accountMenuOpen ? 'Tutup menu profil' : 'Buka menu profil'\"", $html);
        $this->assertStringContainsString('id="desktop-profile-trigger"', $html);
        $this->assertStringContainsString('aria-controls="desktop-profile-menu"', $html);
        $this->assertStringContainsString('id="desktop-profile-menu"', $html);
        $this->assertStringContainsString('aria-labelledby="desktop-profile-trigger"', $html);
        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        $this->assertStringContainsString('data-account-menu-key="profile"', $html);
        $this->assertStringContainsString('data-account-menu-key="likes"', $html);
        $this->assertStringContainsString('data-account-menu-key="settings"', $html);
        $this->assertStringContainsString('data-account-menu-key="sign-out"', $html);
        $this->assertStringContainsString('@keydown.tab="trapOverlayFocus($event, $el)"', $html);
        $this->assertStringContainsString('@click.self="closeAccountMenu()"', $html);
        $this->assertStringContainsString('aria-label="Cari"', $html);
        $this->assertStringContainsString('aria-label="Aktivitas"', $html);
        $this->assertStringContainsString('skuy-profile-icon', $html);
        $this->assertStringContainsString('aria-label="Buat Utas"', $html);
        $this->assertStringContainsString(':aria-pressed="darkMode"', $html);
        $this->assertStringContainsString(":aria-label=\"darkMode ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'\"", $html);
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('feed'), '/').'"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="'.preg_quote(route('search'), '/').'"[^>]*aria-current="page"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="'.preg_quote(route('activity.index'), '/').'"[^>]*aria-current="page"/', $html);

        $bottomNavStart = strpos($html, '<nav class="skuy-mobile-nav"');
        $this->assertNotFalse($bottomNavStart);

        $this->assertSame(
            ['feed', 'search', 'activity', 'messages', 'profile', 'saved', 'dashboard', 'announcements'],
            $this->attributeValues($xpath, '//nav[@data-navigation-kind="responsive"]//*[@data-nav-surfaces="desktop-primary" or contains(@data-nav-surfaces, "desktop-primary mobile-secondary")]/@data-nav-key'),
        );
        $this->assertSame(
            ['profile', 'saved', 'dashboard', 'announcements'],
            $this->attributeValues($xpath, '//nav[@data-navigation-kind="responsive"]//*[@data-nav-surfaces="mobile-secondary" or contains(@data-nav-surfaces, "desktop-primary mobile-secondary")]/@data-nav-key'),
        );
        $this->assertSame(
            ['home', 'search', 'create', 'activity', 'messages'],
            $this->attributeValues($xpath, '//nav[@data-navigation-kind="mobile-primary"]//*[@data-mobile-nav-key]/@data-mobile-nav-key'),
        );
        $this->assertSame(1, $xpath->query('//*[@data-nav-key="announcements"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-nav-key="saved"]')->length);
        $this->assertSame(0, $xpath->query('//nav[@data-navigation-kind="responsive"]//*[@data-nav-surfaces="mobile-secondary" or contains(@data-nav-surfaces, "desktop-primary mobile-secondary")][@data-nav-key="feed" or @data-nav-key="search" or @data-nav-key="activity" or @data-nav-key="messages"]')->length);
        $this->assertSame(1, $xpath->query('//nav[@data-navigation-kind="responsive"]//*[@data-rail-slot="create"]')->length);
        $this->assertSame(
            ['profile', 'likes', 'settings', 'language', 'sign-out'],
            $this->attributeValues($xpath, '//*[@id="mobile-profile-menu"]//*[@data-account-menu-key]/@data-account-menu-key'),
        );
        $this->assertSame(1, $xpath->query('//*[@id="mobile-profile-menu"]//a[@data-account-menu-key="likes" and @href="'.route('user.profile', ['username' => $user->username, 'tab' => 'likes']).'"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="rail-account-menu"]//a[@data-account-menu-key="likes" and @href="'.route('user.profile', ['username' => $user->username, 'tab' => 'likes']).'"]')->length);

        $railSlots = $this->attributeValues($xpath, '//*[@id="mobile-navigation"]//*[@data-rail-slot]/@data-rail-slot');
        $this->assertSame(['desktop-primary', 'create', 'create-divider', 'spacer', 'account'], $railSlots);
        $this->assertSame(1, $xpath->query('//nav[@data-rail-slot="desktop-primary"]//*[@data-rail-slot="create"]')->length);
        $savedPosition = strpos($html, 'data-nav-key="saved"');
        $createPosition = strpos($html, 'data-rail-slot="create"');
        $dashboardPosition = strpos($html, 'data-nav-key="dashboard"');
        $this->assertIsInt($savedPosition);
        $this->assertIsInt($createPosition);
        $this->assertIsInt($dashboardPosition);
        $this->assertLessThan($createPosition, $savedPosition);
        $this->assertLessThan($dashboardPosition, $createPosition);
        $this->assertStringContainsString('flex: 1 0 24px;', file_get_contents(resource_path('css/skuy-prism-navigation.css')));
    }

    public function test_mobile_wallet_aria_label_uses_the_current_locale_for_points(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $response = $this->actingAs($user)->get(route('feed'));
        $response->assertOk();

        $xpath = $this->xpath((string) $response->getContent());
        $wallet = $xpath->query('//*[@class="skuy-mobile-topbar-wallet"]')->item(0);

        $this->assertNotNull($wallet);
        $this->assertSame('0 Poin', $wallet->getAttribute('aria-label'));
    }

    public function test_mobile_account_menu_shows_identity_and_only_account_destinations(): void
    {
        $user = User::factory()->create([
            'name' => 'Navigation Account Owner',
            'email' => 'navigation-owner@example.test',
        ]);

        $html = $this->actingAs($user)->get(route('feed'))->assertOk()->getContent();
        $xpath = $this->xpath($html);
        $accountMenu = $xpath->query('//*[@id="mobile-profile-menu"]')->item(0);

        $this->assertNotNull($accountMenu);
        $this->assertStringContainsString($user->name, $accountMenu->textContent);
        $this->assertStringContainsString($user->email, $accountMenu->textContent);
        $this->assertSame(0, $xpath->query('//*[@id="mobile-profile-menu"]//*[@data-nav-key or @data-mobile-nav-key]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="mobile-profile-menu"]//*[contains(@href, "'.route('bookmarks.index').'") or contains(@href, "'.route('messages.index').'") or contains(@href, "'.route('announcements.index').'")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="mobile-profile-menu"]//*[@data-rail-slot="create"]')->length);
    }

    public function test_mobile_header_and_feed_spacing_reserve_only_the_fixed_header_height(): void
    {
        $css = file_get_contents(resource_path('css/skuy-prism-navigation.css'));
        $prismCss = file_get_contents(resource_path('css/skuy-prism.css'));
        $this->assertIsString($css);
        $this->assertIsString($prismCss);

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 767px\)\s*\{.*?\.skuy-navigation-shell\[data-skuy-prism-shell\] \.skuy-topbar\s*\{[^}]*position: fixed;[^}]*top: 0;[^}]*z-index: 65;[^}]*padding-block-start: env\(safe-area-inset-top\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-app-shell \.skuy-navigation-shell\[data-skuy-prism-shell\] \+ \.skuy-app-shell__workspace\s*\{[^}]*padding-top: calc\(58px \+ env\(safe-area-inset-top\)\);/s',
            $css,
        );
        $this->assertStringNotContainsString('position: sticky;\n        top: 0;\n        z-index: 65;', $css);
        $this->assertStringContainsString('padding-top: 12px;', $prismCss);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function attributeValues(DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        $this->assertNotFalse($nodes);

        return array_map(static fn (\DOMAttr $attribute): string => $attribute->value, iterator_to_array($nodes));
    }

    public function test_create_thread_rail_cta_has_theme_safe_hover_pressed_and_focus_states(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('feed'));
        $css = file_get_contents(resource_path('css/app.css'));

        $response
            ->assertOk()
            ->assertSeeText(__('navigation.create_thread'));

        $xpath = $this->xpath($response->getContent());
        $cta = $xpath->query('//a[@data-desktop-create-cta and contains(concat(" ", normalize-space(@class), " "), " skuy-app-rail__compose ")]');
        $this->assertNotFalse($cta);
        $this->assertSame(1, $cta->length);
        $ctaElement = $cta->item(0);
        $this->assertInstanceOf(\DOMElement::class, $ctaElement);
        $this->assertSame(route('feed').'#composer', $ctaElement->getAttribute('href'));
        $this->assertSame(__('navigation.create_thread'), trim($ctaElement->textContent));
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
