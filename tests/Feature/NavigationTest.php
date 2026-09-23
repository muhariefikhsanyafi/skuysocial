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
            ->assertSeeText(__('home.dashboard.greeting', ['name' => $user->name]))
            ->assertSee('href="'.route('feed').'#composer"', false)
            ->assertSee('href="'.route('user.profile', $user->username).'"', false)
            ->assertSee('href="'.route('search').'"', false)
            ->assertSee('href="'.route('activity.index').'"', false)
            ->assertSee('href="'.route('bookmarks.index').'"', false)
            ->assertSee('id="dashboard-title"', false)
            ->assertSee('aria-current="page"', false);

        $this->assertSame(1, $headingCount);
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
