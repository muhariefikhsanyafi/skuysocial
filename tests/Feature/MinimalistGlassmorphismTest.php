<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class MinimalistGlassmorphismTest extends TestCase
{
    public function test_feed_and_profile_use_neutral_canvases_and_no_decorative_gradients(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/skuy-arah-b.css');

        $this->assertIsString($styles);
        $this->assertStringContainsString('--arah-b-bg: #ffffff;', $styles);
        $this->assertStringContainsString('--arah-b-bg: #101114;', $styles);
        $this->assertDoesNotMatchRegularExpression('/(?:radial|conic|linear)-gradient\s*\(/i', $styles);
    }

    public function test_feed_and_profile_keep_lightweight_glass_and_responsive_composition(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/skuy-arah-b.css');

        $this->assertIsString($styles);
        $this->assertStringContainsString('backdrop-filter: blur(6px);', $styles);
        $this->assertStringContainsString('.skuy-app-shell[data-skuy-arah-b-pages] .skuy-feed-composer', $styles);
        $this->assertStringContainsString('.skuy-profile-header', $styles);
        $this->assertStringContainsString('@media (max-width: 640px)', $styles);
        $this->assertStringContainsString('.skuy-mobile-nav', $styles);
    }

    public function test_dialog_controls_and_desktop_account_menu_stack_are_neutral_glass(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/skuy-arah-b.css');

        $this->assertIsString($styles);
        $this->assertTrue(str_contains($styles, '.skuy-app-shell[data-skuy-arah-b-pages] .skuy-lightbox__control'));
        $this->assertTrue(str_contains($styles, '.skuy-app-shell[data-skuy-arah-b-pages].skuy-desktop-account-menu-open .skuy-app-rail'));
        $this->assertStringContainsString('z-index: 120;', $styles);
    }

    public function test_v2_token_sheet_has_plain_light_and_dark_canvases_with_separate_surface_primitives(): void
    {
        $tokensPath = dirname(__DIR__, 2).'/resources/css/skuy-tokens.css';
        $this->assertFileExists($tokensPath);
        $tokens = file_get_contents($tokensPath);

        $this->assertIsString($tokens);
        $this->assertStringContainsString('--sk-bg: #FFFFFF;', $tokens);
        $this->assertStringContainsString('--sk-bg: #101114;', $tokens);
        $this->assertStringContainsString('--sk-success-bg:', $tokens);
        $this->assertStringContainsString('.sk-surface {', $tokens);
        $this->assertStringContainsString('.sk-glass {', $tokens);
        $this->assertStringContainsString('backdrop-filter: blur(6px);', $tokens);
        $this->assertDoesNotMatchRegularExpression('/\.sk-surface\s*\{[^}]*backdrop-filter/s', $tokens);
    }

    public function test_v2_layer_styles_cover_social_auth_and_state_surfaces_with_transparency_fallback(): void
    {
        $stylesPath = dirname(__DIR__, 2).'/resources/css/skuy-design-v2.css';
        $this->assertFileExists($stylesPath);
        $styles = file_get_contents($stylesPath);

        $this->assertIsString($styles);
        $this->assertStringContainsString('data-skuy-v2-page="feed"', $styles);
        $this->assertStringContainsString('data-skuy-v2-page="profile"', $styles);
        $this->assertStringContainsString('data-skuy-v2-page="saved"', $styles);
        $this->assertStringContainsString('data-skuy-v2-page="post-detail"', $styles);
        $this->assertStringContainsString('skuy-auth-content', $styles);
        $this->assertStringContainsString('background: var(--sk-success-bg);', $styles);
        $this->assertStringContainsString('skuy-saved-empty', $styles);
        $this->assertStringContainsString('skuy-feed-composer', $styles);
        $this->assertStringContainsString('prefers-reduced-transparency: reduce', $styles);
        $this->assertDoesNotMatchRegularExpression('/(?:radial|conic|linear)-gradient\s*\(/i', $styles);
        $this->assertStringContainsString('html[data-skuy-v2-page="welcome"]', $styles);
        $this->assertStringContainsString('html[data-skuy-v2-page^="auth-"]', $styles);
    }

    public function test_v2_feed_cards_and_mobile_actions_follow_flat_surface_and_safe_area_rules(): void
    {
        $styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/skuy-arah-b.css');

        $this->assertIsString($styles);
        $this->assertMatchesRegularExpression(
            '/\.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-app-rail__inner,\s*\.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-post,\s*[^{}]*\{[^}]*backdrop-filter:\s*none;/s',
            $styles,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-post__options > \.skuy-action\s*\{[^}]*width:\s*44px;[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/s',
            $styles,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-topbar__inner\s*\{[^}]*height:\s*calc\(60px \+ env\(safe-area-inset-top, 0px\)\);[^}]*padding:\s*env\(safe-area-inset-top, 0px\) 16px 0;/s',
            $styles,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-profile-page\[data-skuy-profile\] \.skuy-profile-post-list > \.skuy-feed-post,\s*\.skuy-app-shell[^{}]*\.skuy-profile-post-list > \.skuy-post\s*\{[^}]*backdrop-filter:\s*blur/s',
            $styles,
        );

        $v2Styles = file_get_contents(dirname(__DIR__, 2).'/resources/css/skuy-design-v2.css');
        $this->assertIsString($v2Styles);
        $this->assertMatchesRegularExpression(
            '/html\[data-skuy-v2-page="profile"\] \.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-profile-primary-link,\s*html\[data-skuy-v2-page="profile"\] \.skuy-app-shell\[data-skuy-arah-b-pages\] \.skuy-profile-links a\s*\{[^}]*min-height:\s*44px;/s',
            $v2Styles,
        );
    }

    public function test_v2_document_theme_attribute_and_route_markers_are_preserved(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'resources/views/layouts/app.blade.php',
            'resources/views/layouts/guest.blade.php',
            'resources/views/welcome.blade.php',
            'resources/views/app.blade.php',
        ] as $relativePath) {
            $layout = file_get_contents($root.'/'.$relativePath);
            $this->assertIsString($layout);
            $this->assertStringContainsString('data-theme', $layout, $relativePath);
        }

        $navigation = file_get_contents($root.'/resources/views/layouts/navigation.blade.php');
        $this->assertIsString($navigation);
        $this->assertStringContainsString('data-navigation-kind="mobile-primary"', $navigation);

        $appLayout = file_get_contents($root.'/resources/views/layouts/app.blade.php');
        $guestLayout = file_get_contents($root.'/resources/views/layouts/guest.blade.php');
        $welcome = file_get_contents($root.'/resources/views/welcome.blade.php');
        $this->assertIsString($appLayout);
        $this->assertIsString($guestLayout);
        $this->assertIsString($welcome);
        $this->assertStringContainsString('data-skuy-v2-page', $appLayout);
        $this->assertStringContainsString('data-skuy-v2-page', $guestLayout);
        $this->assertStringContainsString('data-skuy-v2-page="welcome"', $welcome);

        $inertiaLayout = file_get_contents($root.'/resources/views/app.blade.php');
        $this->assertIsString($inertiaLayout);
        $this->assertStringContainsString("request()->routeIs('posts.show')", $inertiaLayout);
        $this->assertStringContainsString('resources/css/skuy-design-v2.css', $inertiaLayout);
    }
}
