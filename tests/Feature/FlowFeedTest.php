<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Tests\TestCase;

class FlowFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow_feed_tabs_render_real_views_and_following_keeps_its_filter_through_cursor_fragments(): void
    {
        $viewer = User::factory()->create(['locale' => 'id']);
        $followed = User::factory()->create();
        $unfollowed = User::factory()->create();
        $viewer->followings()->attach($followed->id);
        $baseTime = Carbon::create(2026, 10, 1, 12, 0, 0);
        $followedPosts = collect();

        for ($number = 1; $number <= 11; $number++) {
            $followedPosts->push($this->createPost($followed, [
                'content' => "Flow followed post {$number}",
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]));
        }

        $unfollowedPost = $this->createPost($unfollowed, [
            'content' => 'Flow post outside following feed',
            'created_at' => $baseTime->copy()->addMinute(),
            'updated_at' => $baseTime->copy()->addMinute(),
        ]);

        $forYouResponse = $this->actingAs($viewer)->get(route('feed'));
        $forYouResponse->assertOk()
            ->assertSee('data-flow-page="feed"', false)
            ->assertSee('data-flow-feed-tabs', false)
            ->assertSee('data-flow-tab="for-you"', false)
            ->assertSee('data-flow-tab="following"', false)
            ->assertSee(__('navigation.for_you'))
            ->assertSee(__('navigation.following_feed'))
            ->assertSee('aria-current="page"', false)
            ->assertSee($unfollowedPost->content);

        $followingResponse = $this->actingAs($viewer)->get(route('feed', ['feed' => 'following']));
        $followingResponse->assertOk()
            ->assertSee('Flow followed post 11')
            ->assertDontSee($unfollowedPost->content);
        $this->assertMatchesRegularExpression(
            '/data-flow-tab="following"[^>]*aria-current="page"/',
            $followingResponse->getContent(),
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-flow-tab="for-you"[^>]*aria-current="page"/',
            $followingResponse->getContent(),
        );

        $pageOne = $followingResponse->viewData('posts');
        $this->assertInstanceOf(CursorPaginator::class, $pageOne);
        $this->assertCount(10, $pageOne->getCollection());
        $this->assertSame(
            $followedPosts->reverse()->take(10)->pluck('id')->all(),
            $pageOne->getCollection()->pluck('id')->all(),
        );

        $nextPageUrl = $pageOne->nextPageUrl();
        $this->assertIsString($nextPageUrl);
        $this->assertStringContainsString('feed=following', $nextPageUrl);

        $pageTwo = $this->actingAs($viewer)
            ->withHeader('X-Feed-Fragment', '1')
            ->get($nextPageUrl);

        $pageTwo->assertOk()->assertJsonPath('has_more', false);
        $fragmentHtml = $pageTwo->json('html');
        $this->assertIsString($fragmentHtml);
        $this->assertStringContainsString($followedPosts->first()->uid, $fragmentHtml);
        $this->assertStringNotContainsString($unfollowedPost->uid, $fragmentHtml);
    }

    public function test_flow_feed_styles_are_scoped_and_use_the_requested_flat_neutral_palette(): void
    {
        $css = file_get_contents(resource_path('css/skuy-flow-feed.css'));
        $profileCss = file_get_contents(resource_path('css/skuy-flow-profile.css'));
        $actionCss = file_get_contents(resource_path('css/skuy-flow-post-actions.css'));
        $this->assertIsString($css);
        $this->assertIsString($profileCss);
        $this->assertIsString($actionCss);

        foreach ([
            'html[data-flow-page="feed"]',
            '--flow-canvas: #ffffff;',
            '--flow-canvas: #0d0e13;',
            '--flow-accent: #6e5bef;',
            'prefers-reduced-motion: reduce',
            'prefers-reduced-transparency: reduce',
            'env(safe-area-inset-bottom',
        ] as $contract) {
            $this->assertStringContainsString($contract, $css, "Missing Flow feed styling contract: {$contract}");
        }

        foreach ([
            'html[data-flow-page="feed"] .skuy-feed-page[data-flow-feed]',
            'html[data-flow-page="profile"] .skuy-profile-page[data-skuy-profile] .skuy-profile-post-list',
            '[data-mobile-primary-action="like"] > .skuy-fa-icon',
            'justify-content: space-between;',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
            'min-width: 44px;',
            'min-height: 44px;',
            '[data-mobile-primary-action="like"][aria-pressed="true"]',
            '.skuy-action--appreciation[aria-pressed="true"]',
            '[data-mobile-primary-action="repost"][aria-pressed="true"]',
            '.skuy-action--bookmark[aria-pressed="true"]',
        ] as $contract) {
            $this->assertStringContainsString($contract, $actionCss, "Missing shared post action styling contract: {$contract}");
        }

        foreach (['--flow-like: #be185d;', '--flow-like: #f472b6;'] as $contract) {
            $this->assertStringContainsString($contract, $profileCss, "Missing Profile like color token: {$contract}");
        }

        $this->assertStringNotContainsString('radial-gradient(', $css);
        $this->assertStringNotContainsString('linear-gradient(', $css);
    }

    public function test_flow_mobile_notification_badge_is_positioned_at_the_bells_upper_right_without_forcing_a_wide_pill(): void
    {
        $flowCss = file_get_contents(resource_path('css/skuy-flow-feed.css'));
        $appCss = file_get_contents(resource_path('css/app.css'));
        $navigationView = file_get_contents(resource_path('views/layouts/navigation.blade.php'));
        $this->assertIsString($flowCss);
        $this->assertIsString($appCss);
        $this->assertIsString($navigationView);

        preg_match('/\.skuy-mobile-nav__icon-wrap\s*\{([^}]*)\}/', $appCss, $iconWrapMatches);
        $iconWrapRule = $iconWrapMatches[1] ?? '';
        $this->assertStringContainsString('position: relative;', $iconWrapRule);
        $this->assertStringContainsString('width: 24px;', $iconWrapRule);
        $this->assertStringContainsString('<span class="skuy-mobile-nav__icon-wrap">', $navigationView);
        $this->assertStringContainsString('<x-fa-icon name="bell"', $navigationView);
        $this->assertStringContainsString('class="skuy-feed-nav__badge skuy-mobile-nav__badge"', $navigationView);

        $this->assertStringContainsString(
            '.skuy-mobile-nav__item > .skuy-mobile-nav__icon-wrap > .skuy-mobile-nav__badge',
            $appCss,
        );
        $this->assertStringNotContainsString('.skuy-mobile-nav__badge', $flowCss);
        $badgeRule = $this->lastCssRule($appCss, '.skuy-mobile-nav__badge');
        foreach ([
            'display: inline-flex;',
            'top: -8px;',
            'right: -14px;',
            'min-width: 18px;',
            'width: max-content;',
            'max-width: none;',
            'min-height: 18px;',
            'padding-inline: 4px;',
            'overflow: visible;',
            'text-overflow: clip;',
            'transform: translate(50%, -25%);',
            'white-space: nowrap;',
            'z-index: 1;',
        ] as $contract) {
            $this->assertStringContainsString($contract, $badgeRule, "Missing mobile notification badge contract: {$contract}");
        }
    }

    public function test_dark_feed_verified_component_uses_violet_fill_and_a_contrasting_checkmark(): void
    {
        $css = file_get_contents(resource_path('css/skuy-flow-feed.css'));
        $component = file_get_contents(resource_path('views/components/verified-badge.blade.php'));
        $feedPartial = file_get_contents(resource_path('views/partials/feed-threads.blade.php'));
        $this->assertIsString($css);
        $this->assertIsString($component);
        $this->assertIsString($feedPartial);

        $this->assertStringContainsString('<x-verified-badge />', $feedPartial);
        $this->assertStringContainsString('data-verified-badge', $component);
        $this->assertStringContainsString('stroke="currentColor"', $component);
        $this->assertStringContainsString(
            'html[data-flow-page="feed"].dark .skuy-feed-page[data-flow-feed] [data-verified-badge]',
            $css,
        );

        $verifiedRule = $this->lastCssRule($css, '[data-verified-badge]');
        foreach ([
            'background: var(--flow-accent-ink);',
            'border-color: var(--flow-accent-ink);',
            'color: var(--flow-canvas);',
            'box-shadow: none;',
        ] as $contract) {
            $this->assertStringContainsString($contract, $verifiedRule, "Missing dark verified badge contract: {$contract}");
        }
    }

    public function test_verified_author_renders_the_actual_badge_in_a_feed_post_header(): void
    {
        $viewer = User::factory()->create();
        $verifiedAuthor = User::factory()->create(['is_verified' => true]);
        $post = $this->createPost($verifiedAuthor, ['content' => 'Verified feed badge fixture post']);

        $response = $this->actingAs($viewer)->get(route('feed'));

        $response->assertOk()
            ->assertSee($post->content)
            ->assertSee('data-verified-badge', false)
            ->assertSee('stroke="currentColor"', false);
    }

    public function test_flow_feed_mobile_chrome_metadata_and_desktop_context_follow_layout_contracts(): void
    {
        $css = file_get_contents(resource_path('css/skuy-flow-feed.css'));
        $feedView = file_get_contents(resource_path('views/feed.blade.php'));
        $this->assertIsString($css);
        $this->assertIsString($feedView);

        $topbarRule = $this->lastCssRule($css, '.skuy-topbar');
        $this->assertStringContainsString('border-radius: 0 0 12px 12px;', $topbarRule);
        $this->assertStringContainsString('border-bottom: 1px solid var(--flow-border);', $topbarRule);

        $mobileNavRule = $this->lastCssRule($css, '.skuy-mobile-nav');
        foreach ([
            'position: fixed;',
            'inset-inline-start: 0;',
            'inset-inline-end: 0;',
            'bottom: 0;',
            'width: 100%;',
            'display: grid;',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
            'border-radius: 14px 14px 0 0;',
            'overflow: visible;',
        ] as $contract) {
            $this->assertStringContainsString($contract, $mobileNavRule, "Missing mobile navigation contract: {$contract}");
        }

        $badgeRule = $this->lastCssRule(file_get_contents(resource_path('css/app.css')), '.skuy-mobile-nav__badge');
        foreach (['min-width: 18px;', 'white-space: nowrap;', 'z-index: 1;'] as $contract) {
            $this->assertStringContainsString($contract, $badgeRule, "Missing mobile notification badge contract: {$contract}");
        }

        $this->assertStringContainsString('<h1 id="feed-title" class="sr-only">', $feedView);
        $this->assertStringContainsString('grid-template-columns: 40px minmax(0, 1fr);', $css);
        $this->assertStringContainsString('grid-template-columns: 36px minmax(0, 1fr);', $css);
        $this->assertStringContainsString('padding-inline-end: 56px;', $this->lastCssRule($css, '.skuy-feed-post__header:has(.skuy-post__options)'));
        $this->assertStringContainsString('max-width: 100%;', $this->lastCssRule($css, '.skuy-post__identity'));
        $this->assertStringContainsString('overflow-wrap: anywhere;', $this->lastCssRule($css, '.skuy-post__display-name'));

        $usernameRule = $this->lastCssRule($css, '.skuy-post__username');
        foreach (['overflow: hidden;', 'text-overflow: ellipsis;', 'white-space: nowrap;'] as $contract) {
            $this->assertStringContainsString($contract, $usernameRule, "Missing post username truncation contract: {$contract}");
        }

        $contextRule = $this->lastCssRule($css, '.skuy-feed-context');
        foreach (['position: static;', 'max-height: none;', 'overflow: visible;'] as $contract) {
            $this->assertStringContainsString($contract, $contextRule, "Missing desktop context scroll contract: {$contract}");
        }
    }

    private function lastCssRule(string $css, string $selector): string
    {
        $matchCount = preg_match_all('/'.preg_quote($selector, '/').'\\s*\\{([^}]*)\\}/s', $css, $matches);
        $this->assertGreaterThan(0, $matchCount, "Missing CSS rule for {$selector}");

        $rule = end($matches[1]);
        $this->assertIsString($rule);

        return $rule;
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        $post = Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Flow feed post',
        ], $attributes));

        $timestamps = array_intersect_key($attributes, [
            'created_at' => true,
            'updated_at' => true,
        ]);

        if ($timestamps !== []) {
            $post->forceFill($timestamps)->saveQuietly();
        }

        return $post;
    }
}
