<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_render_feed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('feed'));

        $response
            ->assertOk()
            ->assertSee('@'.$user->username, false)
            ->assertDontSee('{{ Auth::user()->username }}', false)
            ->assertSee('for="composer-topic"', false)
            ->assertSee('id="composer-topic"', false)
            ->assertDontSee('class="skuy-composer__topic-input" placeholder=', false);
        $response->assertViewHas('posts', function ($posts): bool {
            return $posts instanceof CursorPaginator;
        });
    }

    public function test_feed_renders_the_skuy_prism_composition_with_real_feed_creators(): void
    {
        $viewer = User::factory()->create();
        $creator = User::factory()->create(['username' => 'prism-feed-creator']);
        $this->createPost($creator, ['content' => 'A real creator from the current feed']);

        $response = $this->actingAs($viewer)->get(route('feed'));

        $response->assertOk()
            ->assertSee('data-skuy-prism-shell', false)
            ->assertSee('data-skuy-desktop-rail', false)
            ->assertSee('data-skuy-prism-feed', false)
            ->assertSee('data-skuy-stories-placeholder', false)
            ->assertSeeText(__('navigation.stories'))
            ->assertSeeText(__('navigation.stories_coming_soon'))
            ->assertDontSee('class="skuy-feed-creator"', false)
            ->assertSee('skuy-feed-composer__entry', false)
            ->assertSee('skuy-feed-post__header', false)
            ->assertSee('skuy-feed-context-creators', false)
            ->assertSee(route('user.profile', $creator), false)
            ->assertSee('A real creator from the current feed');

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        @$document->loadHTML((string) $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $stories = $xpath->query('//*[@data-skuy-stories-placeholder]');

        $this->assertNotFalse($stories);
        $this->assertSame(1, $stories->length);
        $this->assertSame(0, $xpath->query('//*[@data-skuy-stories-placeholder]//a | //*[@data-skuy-stories-placeholder]//button')->length);
        $this->assertSame(0, $xpath->query('//*[@data-skuy-stories-placeholder]//*[@data-creator-id or @data-user-id]')->length);
        $this->assertStringNotContainsString('skuy-feed-creator', $stories->item(0)->textContent);
    }

    public function test_mobile_primary_navigation_has_exactly_five_items_in_product_order(): void
    {
        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer)->get(route('feed'));
        $html = (string) $response->getContent();

        preg_match_all('/data-mobile-nav-key="([^"]+)"/', $html, $matches);

        $this->assertSame(['home', 'search', 'create', 'activity', 'messages'], $matches[1]);
        $response->assertSee('skuy-app-rail__account', false);
    }

    public function test_skuy_prism_visual_tokens_and_performance_guards_are_defined(): void
    {
        $css = file_get_contents(resource_path('css/skuy-prism.css'));
        $this->assertIsString($css);

        foreach ([
            '--skuy-prism-glass-edge:',
            '--skuy-motion-fast:',
            '--skuy-motion-panel:',
            '.skuy-app-shell__workspace',
            '.skuy-feed-page[data-skuy-prism-feed] .skuy-feed-post',
            '.skuy-post-detail-page[data-skuy-prism-detail] .skuy-post-detail__layout',
            '.skuy-feed-page[data-skuy-prism-feed] .skuy-feed-post.skuy-social-post .skuy-post__header {',
            '.skuy-post-detail-page[data-skuy-prism-detail] .skuy-post--detail-root.skuy-social-post .skuy-post__header {',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
            '@media (min-width: 1200px)',
            '@media (prefers-reduced-motion: reduce)',
            '@media (prefers-reduced-transparency: reduce)',
        ] as $contract) {
            $this->assertStringContainsString($contract, $css, "Missing Skuy Prism visual contract: {$contract}");
        }

        $postSelector = '.skuy-feed-page[data-skuy-prism-feed] .skuy-feed-post {';
        $postStart = strpos($css, $postSelector);
        $this->assertIsInt($postStart, "Missing scoped Feed surface selector: {$postSelector}");
        $postEnd = strpos($css, '}', $postStart);
        $this->assertIsInt($postEnd, 'Unclosed Skuy Prism Feed post surface rule.');
        $postSurfaceRule = substr($css, $postStart, $postEnd - $postStart + 1);
        $this->assertStringNotContainsString('backdrop-filter:', $postSurfaceRule);

        $desktopMediaStart = strpos($css, '@media (min-width: 1280px) {');
        $this->assertIsInt($desktopMediaStart, 'Missing desktop Skuy Prism rail breakpoint.');
        $desktopTopbarSelector = '.skuy-navigation-shell[data-skuy-prism-shell] .skuy-topbar {';
        $desktopTopbarStart = strpos($css, $desktopTopbarSelector, $desktopMediaStart);
        $this->assertIsInt($desktopTopbarStart, 'Missing high-specificity desktop Skuy Prism topbar rule: '.$desktopTopbarSelector);
        $desktopTopbarEnd = strpos($css, '}', $desktopTopbarStart);
        $this->assertIsInt($desktopTopbarEnd, 'Unclosed desktop Skuy Prism topbar rule.');
        $desktopTopbarRule = substr($css, $desktopTopbarStart, $desktopTopbarEnd - $desktopTopbarStart + 1);
        $this->assertStringContainsString('inset-inline-start: auto;', $desktopTopbarRule);
        $this->assertStringContainsString('inset-inline-end: 18px;', $desktopTopbarRule);
        $this->assertStringContainsString('width: max-content;', $desktopTopbarRule);
        $this->assertStringContainsString('border: 0;', $desktopTopbarRule);
        $this->assertStringContainsString('background: transparent;', $desktopTopbarRule);
        $this->assertStringContainsString('box-shadow: none;', $desktopTopbarRule);
        $this->assertStringContainsString('-webkit-backdrop-filter: none;', $desktopTopbarRule);
        $this->assertStringContainsString('backdrop-filter: none;', $desktopTopbarRule);

        $communityLinkSelector = '.skuy-feed-page[data-skuy-prism-feed] .skuy-feed-context-card--community a {';
        $communityLinkStart = strpos($css, $communityLinkSelector);
        $this->assertIsInt($communityLinkStart, 'Missing Feed community-card action rule.');
        $communityLinkEnd = strpos($css, '}', $communityLinkStart);
        $this->assertIsInt($communityLinkEnd, 'Unclosed Feed community-card action rule.');
        $communityLinkRule = substr($css, $communityLinkStart, $communityLinkEnd - $communityLinkStart + 1);
        $this->assertStringContainsString('color: var(--skuy-on-brand);', $communityLinkRule);
    }

    public function test_feed_context_height_reserves_the_grid_tail_in_both_menu_states(): void
    {
        $css = file_get_contents(resource_path('css/skuy-prism.css'));
        $navigationCss = file_get_contents(resource_path('css/skuy-prism-navigation.css'));
        $appCss = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertIsString($navigationCss);
        $this->assertIsString($appCss);
        $this->assertTrue(str_contains($css, '--skuy-feed-shell-bottom-padding: 56px;'), 'The grid tail must share the Feed shell padding token.');
        $this->assertTrue(str_contains($css, '--skuy-feed-context-bottom-reserve: calc(var(--skuy-feed-context-bottom-gap) + var(--skuy-feed-shell-bottom-padding));'));
        $this->assertTrue(str_contains($css, '--skuy-feed-context-bottom-gap: 16px;'), 'The context rail must reserve a visible bottom gap.');

        $feedShellSelector = '.skuy-feed-page[data-skuy-prism-feed] .skuy-feed-shell {';
        $desktopFeedMediaStart = strpos($css, '@media (min-width: 1200px) {');
        $feedShellStart = strpos($css, $feedShellSelector, $desktopFeedMediaStart);
        $this->assertIsInt($feedShellStart, 'Missing desktop Feed shell sizing rule.');
        $feedShellEnd = strpos($css, '}', $feedShellStart);
        $feedShellRule = substr($css, $feedShellStart, $feedShellEnd - $feedShellStart + 1);
        $this->assertTrue(str_contains($feedShellRule, 'padding-bottom: var(--skuy-feed-shell-bottom-padding);'));

        $contextSelector = '.skuy-feed-page[data-skuy-prism-feed] .skuy-feed-context {';
        $contextStart = strrpos($css, $contextSelector);
        $this->assertIsInt($contextStart, 'Missing desktop Feed context scroll rule.');
        $contextEnd = strpos($css, '}', $contextStart);
        $contextRule = substr($css, $contextStart, $contextEnd - $contextStart + 1);
        $this->assertTrue(str_contains($contextRule, 'var(--skuy-feed-context-bottom-reserve)'));

        $openContextSelector = '.skuy-app-shell.skuy-desktop-account-menu-open .skuy-feed-context {';
        $openContextStart = strrpos($css, $openContextSelector);
        $this->assertIsInt($openContextStart, 'Missing account-menu-open Feed context sizing rule.');
        $openContextEnd = strpos($css, '}', $openContextStart);
        $openContextRule = substr($css, $openContextStart, $openContextEnd - $openContextStart + 1);
        $this->assertTrue(str_contains($openContextRule, 'var(--skuy-feed-context-bottom-reserve)'));

        $this->assertFalse(
            str_contains($navigationCss, '.skuy-navigation-shell[data-skuy-prism-shell] .skuy-topbar,'),
            'The desktop topbar must not inherit the global rectangular glass-surface group.'
        );
        $this->assertFalse(
            str_contains($css, '.skuy-navigation-shell[data-skuy-prism-shell] .skuy-topbar,'),
            'Reduced-transparency styles must not repaint the desktop topbar wrapper.'
        );
        $this->assertFalse(
            str_contains($appCss, "@media (prefers-reduced-transparency: reduce) {\n    .skuy-topbar,"),
            'The generic reduced-transparency rule must not repaint the desktop topbar wrapper.'
        );
        $this->assertTrue(str_contains($appCss, '@media (max-width: 1279px) and (prefers-reduced-transparency: reduce) {'));
        $this->assertTrue(str_contains($navigationCss, '@media (min-width: 1280px) and (prefers-reduced-transparency: reduce) {'));
    }

    public function test_feed_keeps_an_accessible_stream_heading_without_a_visible_feed_title(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $this->createPost($author, ['content' => 'Accessible stream heading contract']);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $response->assertOk()->assertSee('class="skuy-feed-page__header"', false);

        $dom = new \DOMDocument();
        $previousLibxmlSetting = libxml_use_internal_errors(true);
        @$dom->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlSetting);

        $heading = $dom->getElementById('feed-title');
        $this->assertInstanceOf(\DOMElement::class, $heading);
        $classTokens = preg_split('/\s+/', trim($heading->getAttribute('class'))) ?: [];

        $this->assertContains('sr-only', $classTokens);
        $this->assertNotContains('skuy-feed-page__title', $classTokens);
        $this->assertSame(__('navigation.feed'), trim($heading->textContent));
    }

    public function test_social_foundations_define_the_compact_spacing_and_type_scale(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        $rootStart = strpos($css, ':root {');
        $this->assertIsInt($rootStart, 'Missing root design-token scope.');
        $rootEnd = strpos($css, '}', $rootStart);
        $this->assertIsInt($rootEnd, 'Unclosed root design-token scope.');
        $rootTokens = substr($css, $rootStart, $rootEnd - $rootStart + 1);

        foreach ([
            '--skuy-space-1: 4px;',
            '--skuy-space-2: 8px;',
            '--skuy-space-3: 12px;',
            '--skuy-space-4: 16px;',
            '--skuy-space-6: 24px;',
            '--skuy-space-8: 32px;',
            '--skuy-space-10: 40px;',
            '--skuy-space-12: 48px;',
            '--skuy-space-16: 64px;',
            '--skuy-type-social-page: 24px;',
            '--skuy-type-social-section: 18px;',
            '--skuy-type-social-surface: 16px;',
            '--skuy-type-social-author: 15px;',
            '--skuy-type-social-body: 15px;',
            '--skuy-type-social-meta: 12px;',
            '--skuy-type-social-label: 12px;',
            '--skuy-icon-social: 18px;',
        ] as $token) {
            $this->assertStringContainsString($token, $rootTokens, "Missing social design token: {$token}");
        }
    }

    public function test_mobile_social_pages_use_four_column_grid_and_twelve_pixel_gutters(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        foreach ([
            '--skuy-social-grid-columns: 4;',
            '--skuy-social-gutter: var(--skuy-space-3);',
            'grid-template-columns: repeat(var(--skuy-social-grid-columns), minmax(0, 1fr));',
        ] as $foundationContract) {
            $this->assertTrue(str_contains($css, $foundationContract), "Missing mobile social foundation: {$foundationContract}");
        }
    }

    public function test_mobile_feed_uses_a_full_width_content_stream_with_compact_author_rows(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        foreach ([
            'position: absolute;',
            'display: block;',
            'width: calc(100% + var(--skuy-social-gutter) + var(--skuy-social-gutter));',
            'margin-inline: calc(0px - var(--skuy-social-gutter));',
            'font-size: var(--skuy-type-social-body);',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
        ] as $cssContract) {
            $this->assertTrue(str_contains($css, $cssContract), "Missing mobile Feed composition contract: {$cssContract}");
        }
    }

    public function test_mobile_social_action_rows_stay_inside_post_content(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $selector = '.skuy-post.skuy-social-post .skuy-actions.skuy-actions--primary {';
        $ruleStart = strrpos($css, $selector);
        $this->assertNotFalse($ruleStart, 'Social action rows need a bounded mobile width rule.');
        $ruleEnd = strpos($css, '}', $ruleStart);
        $rule = substr($css, $ruleStart, $ruleEnd - $ruleStart);

        foreach ([
            'display: grid;',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
            'width: 100%;',
            'max-width: 100%;',
            'margin-inline-start: 0;',
        ] as $declaration) {
            $this->assertTrue(str_contains($rule, $declaration), "Social action row must include {$declaration}");
        }
    }

    public function test_mobile_social_action_icons_use_the_normalized_size_token(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $selector = '.skuy-post.skuy-social-post .skuy-actions.skuy-actions--primary .skuy-action > svg,';
        $ruleStart = strrpos($css, $selector);
        $this->assertNotFalse($ruleStart, 'Mobile social action icons need a high-specificity size rule.');
        $ruleEnd = strpos($css, '}', $ruleStart);
        $rule = substr($css, $ruleStart, $ruleEnd - $ruleStart);

        foreach (['width: var(--skuy-icon-social);', 'height: var(--skuy-icon-social);'] as $declaration) {
            $this->assertTrue(str_contains($rule, $declaration), "Social icon rule must include {$declaration}");
        }
    }

    public function test_active_feed_like_is_red_and_appreciation_remains_gold(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $this->assertTrue(
            str_contains($css, '.skuy-social-post .skuy-actions--primary [data-mobile-primary-action="like"][aria-pressed="true"]'),
            'The selected Feed Like must have a dedicated semantic color rule.',
        );
        $this->assertTrue(str_contains($css, 'color: var(--skuy-danger);'), 'Selected Like must use the red danger token.');
        $this->assertTrue(
            str_contains($css, ".skuy-actions--primary .skuy-appreciation--inline .skuy-action--appreciation[aria-pressed='true']"),
            'Selected Appreciation must retain its gold semantic rule.',
        );
        $this->assertTrue(str_contains($css, 'color: var(--skuy-reaction);'), 'Selected Appreciation must use the gold reaction token.');
    }

    public function test_feed_visual_hooks_keep_composer_and_social_controls_real_and_present(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Feed visual regression post']);
        $rootComment = $this->createComment($post, $author, 'Feed visual regression root');
        $this->createComment($post, $author, 'Feed visual regression reply', ['parent_id' => $rootComment->id]);
        $this->createPost($author, [
            'content' => 'Feed visual regression media post',
            'images' => ['feed-visual-regression.jpg'],
        ]);
        $this->createPost($author, [
            'content' => 'Feed visual regression carousel post',
            'images' => ['feed-slide-one.jpg', 'feed-slide-two.jpg'],
        ]);
        $this->createPost($viewer, [
            'repost_id' => $post->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $html = (string) $response->getContent();

        $response->assertOk();
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'data-feed-post-uid="'.$post->uid.'"'),
            'The original post and its repost should exercise duplicate Feed instances.',
        );
        foreach ([
            'class="skuy-composer skuy-feed-composer"',
            'class="skuy-feed-timeline"',
            'for="feed-composer-body"',
            'id="feed-composer-body"',
            'id="feed-composer-images"',
            'for="feed-composer-images"',
            'name="images[]"',
            'accept="image/*"',
            'class="skuy-post skuy-feed-post skuy-social-post cursor-pointer"',
            'class="skuy-post__repost skuy-feed-post__repost"',
            'class="skuy-actions skuy-actions--primary skuy-feed-post__actions"',
            "@click=\"if(!\$event.target.closest('button, a, form, img, input, textarea, select, [contenteditable]')) window.location=",
            'toggleLike($event)',
            'toggleRepost($event)',
            'toggleBookmark($event)',
            'data-feed-post-action="like"',
            'data-feed-post-action="repost"',
            'data-feed-post-action="bookmark"',
            'bookmarkFormAction()',
            'isRepostRequestInFlight',
            'isBookmarkRequestInFlight',
            'syncPeerState($event)',
            'feedCommentControl({',
            '@feed-comment-created="commentCreated($event)"',
            'feedCommentComposer({',
            'data-feed-created-roots',
            'data-feed-comment-id=',
            'data-feed-reply-list',
            'data-feed-comment-count',
            'data-feed-more-comments',
            'class="skuy-comment-preview skuy-feed-post__comment-preview"',
            'class="skuy-feed-post__media skuy-feed-post__media--single"',
            'class="skuy-feed-post__media skuy-feed-post__media--carousel"',
            'class="skuy-media-track skuy-feed-media-track--single"',
            'class="skuy-media-track skuy-feed-media-track--carousel"',
            'data-media-slider="true"',
            'data-media-slide="true"',
            'data-media-indicator="true"',
            'class="skuy-context skuy-feed-context"',
        ] as $markupHook) {
            $this->assertTrue(str_contains($html, $markupHook), "Missing Feed presentation hook: {$markupHook}");
        }
    }

    public function test_feed_distinguishes_single_media_from_carousel_and_limits_pager_to_multiple_images(): void
    {
        $user = User::factory()->create();
        $this->createPost($user, [
            'content' => 'Feed single-media distinction',
            'images' => ['feed-single.jpg'],
        ]);

        $singleResponse = $this->actingAs($user)->get(route('feed'));
        $singleResponse
            ->assertOk()
            ->assertSee('skuy-feed-post__media--single', false)
            ->assertSee('skuy-feed-media-track--single', false)
            ->assertDontSee('skuy-feed-media-track--carousel', false)
            ->assertDontSee('skuy-feed-media-item--portrait-preview', false)
            ->assertDontSee('data-media-indicator="true"', false);

        $this->createPost($user, [
            'content' => 'Feed carousel distinction',
            'images' => ['feed-slide-one.jpg', 'feed-slide-two.jpg', 'feed-slide-three.jpg'],
        ]);

        $carouselResponse = $this->actingAs($user)->get(route('feed'));
        $carouselResponse
            ->assertOk()
            ->assertSee('skuy-feed-post__media--carousel', false)
            ->assertSee('skuy-feed-media-track--carousel', false)
            ->assertSee('skuy-feed-media-item--portrait-preview', false)
            ->assertSee('data-media-indicator="true"', false)
            ->assertSee('data-media-indicator-dot="true"', false)
            ->assertSee("querySelectorAll('[data-media-slide=true]')", false)
            ->assertSee('this.activeSlide = nearestIndex;', false);
    }

    public function test_feed_landscape_and_portrait_sources_share_portrait_carousel_previews(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $landscape = UploadedFile::fake()->image('feed-landscape.jpg', 1600, 900)->store('feed-previews', 'public');
        $portrait = UploadedFile::fake()->image('feed-portrait.jpg', 900, 1600)->store('feed-previews', 'public');

        $this->assertSame([1600, 900], array_slice(getimagesize(Storage::disk('public')->path($landscape)), 0, 2));
        $this->assertSame([900, 1600], array_slice(getimagesize(Storage::disk('public')->path($portrait)), 0, 2));

        $this->createPost($user, [
            'content' => 'Mixed source aspect ratios',
            'images' => [$landscape, $portrait],
        ]);

        $response = $this->actingAs($user)->get(route('feed'));
        $html = (string) $response->getContent();

        $response
            ->assertOk()
            ->assertSee('skuy-feed-post__media--carousel', false)
            ->assertSee(asset('storage/'.$landscape), false)
            ->assertSee(asset('storage/'.$portrait), false);
        $this->assertSame(2, substr_count($html, 'class="skuy-media-item skuy-feed-media-item--portrait-preview"'));
    }

    public function test_premium_feed_styles_are_scoped_and_preserve_responsive_controls(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        foreach ([
            '.skuy-feed-page .skuy-feed-shell',
            '.skuy-feed-page .skuy-feed-content',
            '.skuy-feed-page .skuy-feed-timeline',
            '.skuy-feed-page .skuy-feed-composer',
            '.skuy-feed-page .skuy-feed-post',
            '.skuy-feed-page .skuy-feed-post__actions',
            '.skuy-feed-page .skuy-feed-context-card',
            '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--carousel',
            'scroll-behavior: smooth;',
            '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--single',
            'max-width: 44rem;',
            'padding: 14px;',
            'grid-template-columns: minmax(0, 1fr) minmax(260px, 280px);',
            '@media (min-width: 1500px)',
            '@media (max-width: 767px)',
            'padding-bottom: calc(var(--skuy-social-mobile-dock-clearance) + env(safe-area-inset-bottom));',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
            'gap: 16px;',
            'gap: 8px;',
            'max-height: 28rem;',
            'max-height: 18rem;',
            'aspect-ratio: auto;',
            'aspect-ratio: 4 / 5;',
            'width: fit-content;',
            'width: max-content;',
            'flex: 0 0 min(66%, 28rem);',
            'flex: 0 0 74%;',
            'flex: 0 0 auto;',
            'overflow-x: auto;',
            'scroll-snap-type: x mandatory;',
            'scroll-snap-type: none;',
            'margin-top: 8px;',
            'background: var(--skuy-surface-raised);',
            'background: var(--skuy-surface-subtle);',
            'border: 1px solid var(--skuy-border-soft);',
            'border-color: var(--skuy-border-strong);',
            'object-fit: contain;',
            'object-fit: cover;',
            'object-position: center;',
            'clip-path: inset(50%);',
            '@media (hover: hover) and (pointer: fine)',
        ] as $cssHook) {
            $this->assertTrue(str_contains($css, $cssHook), "Missing Feed style hook: {$cssHook}");
        }
    }

    public function test_feed_posts_have_compact_surfaces_without_nested_action_panels(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        foreach ([
            '.skuy-feed-page .skuy-feed-timeline {' => [
                'display: grid;',
                'min-width: 0;',
                'gap: 16px;',
            ],
            '.skuy-feed-page .skuy-feed-composer {' => [
                'padding: 14px;',
                'border-color: var(--skuy-border-strong);',
                'background: var(--skuy-surface-raised);',
                'box-shadow: var(--skuy-shadow-soft);',
            ],
            '.dark .skuy-feed-page .skuy-feed-composer {' => [
                'border-color: var(--skuy-border-soft);',
            ],
            '.skuy-feed-page .skuy-feed-post {' => [
                'padding: 12px 14px;',
                'border: 1px solid var(--skuy-border-soft);',
                'border-radius: var(--radius-small);',
                'background: var(--skuy-surface-raised);',
                'box-shadow: none;',
            ],
            '.dark .skuy-feed-page .skuy-feed-post,' => [
                'background: var(--skuy-surface-subtle);',
            ],
            '.skuy-feed-page .skuy-feed-post__media,' => [
                'width: 100%;',
                'min-width: 0;',
                'margin: 8px 0;',
            ],
            '.skuy-feed-page .skuy-feed-post__media .skuy-media-track,' => [
                'align-items: stretch;',
                'gap: 8px;',
                'padding-bottom: 0;',
            ],
            '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--carousel' => [
                'scroll-behavior: smooth;',
            ],
            '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--single,' => [
                'overflow: hidden;',
                'scroll-snap-type: none;',
            ],
            '.skuy-feed-page .skuy-feed-post__media .skuy-media-item,' => [
                'display: flex;',
                'flex: 0 0 auto;',
                'width: fit-content;',
                'aspect-ratio: auto;',
                'border: 0;',
                'border-radius: var(--radius-small);',
                'background: transparent;',
            ],
            '.skuy-feed-page .skuy-feed-post__media--carousel .skuy-feed-media-item--portrait-preview,' => [
                'flex: 0 0 min(66%, 28rem);',
                'aspect-ratio: 4 / 5;',
                'height: auto;',
                'overflow: hidden;',
                'border-radius: var(--radius-small);',
            ],
            '.skuy-feed-page .skuy-feed-post__media--carousel .skuy-feed-media-item--portrait-preview img,' => [
                'width: 100%;',
                'height: 100%;',
                'object-fit: cover;',
                'object-position: center;',
            ],
            '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--single .skuy-media-item,' => [
                'flex: 0 1 auto;',
                'width: max-content;',
                'max-width: 100%;',
                'height: auto;',
                'max-height: 28rem;',
            ],
            '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--single .skuy-media-item img,' => [
                'width: auto;',
                'height: auto;',
                'max-width: 100%;',
                'max-height: 28rem;',
                'object-fit: contain;',
            ],
            '.skuy-feed-page .skuy-composer__topic {' => [
                'border: 0;',
                'background: transparent;',
                'padding: 0;',
            ],
            '.skuy-feed-page .skuy-composer__textarea {' => [
                'border: 0;',
                'background: transparent;',
            ],
            '.skuy-feed-page .skuy-feed-post__actions {' => [
                'display: flex;',
                'justify-content: flex-start;',
                'border: 0;',
                'background: transparent;',
                'padding: 0;',
            ],
        ] as $selector => $declarations) {
            $ruleStart = strpos($css, $selector);
            $this->assertIsInt($ruleStart, "Missing CSS rule: {$selector}");
            $ruleEnd = strpos($css, '}', $ruleStart);
            $this->assertIsInt($ruleEnd, "Unclosed CSS rule: {$selector}");
            $rule = substr($css, $ruleStart, $ruleEnd - $ruleStart + 1);

            foreach ($declarations as $declaration) {
                $this->assertTrue(
                    str_contains($rule, $declaration),
                    "Missing declaration {$declaration} from {$selector}",
                );
            }
        }

        $feedCssStart = strpos($css, '/* Feed-only timeline and separate single/carousel media presentation. */');
        $this->assertIsInt($feedCssStart, 'Missing Feed CSS scope');
        $feedCss = substr($css, $feedCssStart);
        $this->assertStringNotContainsString('aspect-ratio: 16 / 9;', $feedCss);
        $this->assertStringNotContainsString('aspect-ratio: 4 / 3;', $feedCss);
    }

    public function test_mobile_feed_uses_flat_stream_posts_and_integrated_comment_previews(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        $mobileBreakpoint = '@media (max-width: 767px)';

        $postSelector = '.skuy-feed-page .skuy-feed-post {';
        $postStart = strrpos($css, $postSelector);
        $this->assertIsInt($postStart, "Missing mobile Feed post rule: {$postSelector}");
        $this->assertIsInt(strrpos(substr($css, 0, $postStart), $mobileBreakpoint), 'Mobile Feed post styles must stay inside the mobile breakpoint.');
        $postEnd = strpos($css, '}', $postStart);
        $this->assertIsInt($postEnd, "Unclosed mobile Feed post rule: {$postSelector}");
        $postRule = substr($css, $postStart, $postEnd - $postStart + 1);
        foreach ([
            'padding: var(--skuy-space-4) 0;',
            'border: 0;',
            'border-bottom: 1px solid var(--skuy-border-subtle);',
            'border-radius: 0;',
            'background: transparent;',
            'box-shadow: var(--skuy-social-post-elevation);',
        ] as $declaration) {
            $this->assertStringContainsString($declaration, $postRule);
        }

        $feedListSelector = '.skuy-feed-page .skuy-feed-list {';
        $feedListStart = strrpos($css, $feedListSelector);
        $this->assertIsInt($feedListStart, "Missing mobile Feed list rule: {$feedListSelector}");
        $this->assertIsInt(strrpos(substr($css, 0, $feedListStart), $mobileBreakpoint), 'Mobile Feed list styles must stay inside the mobile breakpoint.');
        $feedListEnd = strpos($css, '}', $feedListStart);
        $this->assertIsInt($feedListEnd, "Unclosed mobile Feed list rule: {$feedListSelector}");
        $this->assertStringContainsString('gap: 0;', substr($css, $feedListStart, $feedListEnd - $feedListStart + 1));

        $previewSelector = '.skuy-feed-page .skuy-feed-post__comment-preview {';
        $previewStart = strrpos($css, $previewSelector);
        $this->assertIsInt($previewStart, "Missing integrated comment preview rule: {$previewSelector}");
        $previewEnd = strpos($css, '}', $previewStart);
        $this->assertIsInt($previewEnd, "Unclosed comment preview rule: {$previewSelector}");
        $previewRule = substr($css, $previewStart, $previewEnd - $previewStart + 1);
        foreach (['margin-top: 4px;', 'padding-top: 8px;', 'border-top: 0;', 'background: transparent;'] as $declaration) {
            $this->assertStringContainsString($declaration, $previewRule);
        }

        $previewCommentSelector = '.skuy-feed-page .skuy-feed-post__comment-preview > .skuy-comment {';
        $previewCommentStart = strrpos($css, $previewCommentSelector);
        $this->assertIsInt($previewCommentStart, "Missing integrated preview comment rule: {$previewCommentSelector}");
        $previewCommentEnd = strpos($css, '}', $previewCommentStart);
        $this->assertIsInt($previewCommentEnd, "Unclosed integrated preview comment rule: {$previewCommentSelector}");
        $previewCommentRule = substr($css, $previewCommentStart, $previewCommentEnd - $previewCommentStart + 1);
        foreach (['padding-left: 8px;', 'border-left: 1px solid var(--skuy-border-soft);', 'background: transparent;'] as $declaration) {
            $this->assertStringContainsString($declaration, $previewCommentRule);
        }
    }

    public function test_feed_mobile_actions_keep_accessible_tap_targets(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $selector = '.skuy-feed-page .skuy-feed-post__actions .skuy-action {';
        $ruleStart = strrpos($css, $selector);
        $this->assertIsInt($ruleStart, "Missing mobile action rule: {$selector}");

        $mobileBreakpoint = strrpos(substr($css, 0, $ruleStart), '@media (max-width: 767px)');
        $this->assertIsInt($mobileBreakpoint, 'Missing mobile Feed breakpoint before action targets');

        $ruleEnd = strpos($css, '}', $ruleStart);
        $this->assertIsInt($ruleEnd, "Unclosed mobile action rule: {$selector}");
        $rule = substr($css, $ruleStart, $ruleEnd - $ruleStart + 1);

        $this->assertStringContainsString('min-width: 44px;', $rule);
        $this->assertStringContainsString('min-height: 44px;', $rule);
    }

    public function test_feed_mobile_carousel_uses_portrait_tiles_and_internal_scrolling(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $selector = '.skuy-feed-page .skuy-feed-post__media--carousel .skuy-feed-media-item--portrait-preview,';
        $ruleStart = strrpos($css, $selector);
        $this->assertIsInt($ruleStart, "Missing mobile carousel item rule: {$selector}");

        $mobileBreakpoint = strrpos(substr($css, 0, $ruleStart), '@media (max-width: 767px)');
        $this->assertIsInt($mobileBreakpoint, 'Missing mobile Feed breakpoint before carousel row styles');

        $ruleEnd = strpos($css, '}', $ruleStart);
        $this->assertIsInt($ruleEnd, "Unclosed mobile carousel item rule: {$selector}");
        $rule = substr($css, $ruleStart, $ruleEnd - $ruleStart + 1);

        $this->assertStringContainsString('flex: 0 0 74%;', $rule);
        $this->assertStringNotContainsString('height: 12rem;', $rule);
        $this->assertStringNotContainsString('aspect-ratio: 1 / 1;', $rule);
        $this->assertStringNotContainsString('aspect-ratio: 4 / 3;', $rule);
        $this->assertStringNotContainsString('aspect-ratio: 16 / 9;', $rule);
        $this->assertStringContainsString('aspect-ratio: 4 / 5;', $css);
        $this->assertStringContainsString('object-fit: cover;', $css);

        $trackSelector = '.skuy-media-track {';
        $trackStart = strpos($css, $trackSelector);
        $this->assertIsInt($trackStart, "Missing shared media scroller: {$trackSelector}");
        $trackEnd = strpos($css, '}', $trackStart);
        $this->assertIsInt($trackEnd, "Unclosed shared media scroller: {$trackSelector}");
        $trackRule = substr($css, $trackStart, $trackEnd - $trackStart + 1);
        $this->assertStringContainsString('overflow-x: auto;', $trackRule);
        $this->assertStringContainsString('scroll-snap-type: x mandatory;', $trackRule);

        $singleSelector = '.skuy-feed-page .skuy-feed-post__media .skuy-feed-media-track--single .skuy-media-item,';
        $singleRuleStart = strrpos($css, $singleSelector);
        $this->assertIsInt($singleRuleStart, "Missing mobile single-image bound: {$singleSelector}");
        $singleRuleEnd = strpos($css, '}', $singleRuleStart);
        $this->assertIsInt($singleRuleEnd, "Unclosed mobile single-image rule: {$singleSelector}");
        $singleRule = substr($css, $singleRuleStart, $singleRuleEnd - $singleRuleStart + 1);
        $this->assertStringContainsString('max-height: 18rem;', $singleRule);

        $timelineSelector = '.skuy-feed-page .skuy-feed-timeline {';
        $timelineStart = strrpos($css, $timelineSelector);
        $this->assertIsInt($timelineStart, "Missing mobile Feed timeline rule: {$timelineSelector}");
        $timelineEnd = strpos($css, '}', $timelineStart);
        $this->assertIsInt($timelineEnd, "Unclosed mobile Feed timeline rule: {$timelineSelector}");
        $timelineRule = substr($css, $timelineStart, $timelineEnd - $timelineStart + 1);
        $this->assertStringContainsString('gap: 0;', $timelineRule);
        $this->assertStringContainsString('padding-bottom: calc(var(--skuy-social-mobile-dock-clearance) + env(safe-area-inset-bottom));', $css);
    }

    public function test_feed_wide_desktop_keeps_portrait_carousel_inside_the_existing_rail(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);

        $breakpoint = strrpos($css, '@media (min-width: 1500px)');
        $this->assertIsInt($breakpoint, 'Missing wide desktop Feed breakpoint');

        $wideLayoutEnd = strpos($css, '}', $breakpoint);
        $this->assertIsInt($wideLayoutEnd, 'Unclosed wide desktop Feed breakpoint');
        $wideLayout = substr($css, $breakpoint, $wideLayoutEnd - $breakpoint + 1);

        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(260px, 280px);', $wideLayout);
        $this->assertStringContainsString('flex: 0 0 min(66%, 28rem);', $css);
        $this->assertStringContainsString('aspect-ratio: 4 / 5;', $css);
        $this->assertStringNotContainsString('height: 20rem;', $wideLayout);
    }

    public function test_feed_like_action_keeps_html_fallback_and_exposes_reactive_hooks(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $response = $this->actingAs($user)->get(route('feed'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $response
            ->assertOk()
            ->assertSee('action="'.route('posts.like', $post).'"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('x-data="likeControl({', false)
            ->assertSee('@submit.prevent="toggleLike($event)"', false)
            ->assertSee(':disabled="isLikeRequestInFlight"', false)
            ->assertSee(':aria-pressed="liked.toString()"', false)
            ->assertSee(':aria-label="likeButtonLabel()"', false)
            ->assertSee(':title="likeButtonLabel()"', false)
            ->assertSee('x-text="likeDisplayCount()"', false)
            ->assertSee('x-show="likeDisplayCount()"', false)
            ->assertSee('feedbackMessage', false)
            ->assertSee('class="min-h-5"', false)
            ->assertSee(':role="feedbackIsError ? \'alert\' : \'status\'"', false)
            ->assertSee(':aria-live="feedbackIsError ? \'assertive\' : \'polite\'"', false);

        $this->assertIsString($javascript);
        $this->assertStringContainsString("credentials: 'same-origin'", $javascript);
        $this->assertStringContainsString('async requestAction(url, method, body = null)', $javascript);
        $this->assertStringContainsString("this.requestAction(this.urls.like, 'POST', { liked: desiredLiked })", $javascript);
        $this->assertStringContainsString('body: body === null ? undefined : JSON.stringify(body)', $javascript);
        $this->assertStringContainsString('if (this.isLikeRequestInFlight) return;', $javascript);
        $this->assertStringContainsString('this.liked = payload.liked;', $javascript);
        $this->assertStringContainsString('this.likeCount = payload.like_count;', $javascript);
    }

    public function test_feed_composer_exposes_native_media_preview_hooks(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('feed'));

        $response->assertOk()
            ->assertSee('name="images[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('accept="image/*"', false)
            ->assertSee('class="skuy-upload skuy-feed-composer__file-input"', false)
            ->assertSee('selectedImages', false)
            ->assertSee('x-ref="imagesInput"', false)
            ->assertSee('@change="handleImageSelection($event)"', false)
            ->assertSee('new DataTransfer()', false)
            ->assertSee('URL.createObjectURL(file)', false)
            ->assertSee('@click="openLightbox(selectedImages.map(item => item.previewUrl), index, $event)"', false)
            ->assertSee(':aria-label="\'Lihat pratinjau gambar \' + (index + 1)"', false)
            ->assertSee('@click="closeImageIfActive(image.previewUrl); removeImage(index)"', false)
            ->assertSee('openLightbox(images, index = 0, event = null)', false)
            ->assertSee('lightboxImages.length > 1', false)
            ->assertSee('@click.stop="previousImage()"', false)
            ->assertSee('@click.stop="nextImage()"', false)
            ->assertSee('event.key === \'ArrowLeft\'', false)
            ->assertSee('event.key === \'ArrowRight\'', false)
            ->assertSee('aria-label="Gambar sebelumnya"', false)
            ->assertSee('aria-label="Gambar selanjutnya"', false)
            ->assertSee('@keydown.window="handleLightboxKeydown($event)"', false)
            ->assertSee(':aria-label="\'Hapus gambar \'', false)
            ->assertSee('Maksimal 4 gambar', false);
    }

    public function test_feed_input_boundaries_use_the_strong_semantic_border_token(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.skuy-composer__topic-input\s*\{[^}]*border-bottom:\s*1px solid var\(--skuy-border-strong\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-upload\s*\{[^}]*border:\s*1px solid var\(--skuy-border-strong\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-inline-input\s*\{[^}]*border:\s*1px solid var\(--skuy-border-strong\)/s',
            $css,
        );
    }

    public function test_feed_post_options_menu_exposes_accessible_controls(): void
    {
        $owner = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($owner)->get(route('feed'));

        $response
            ->assertOk()
            ->assertSee('id="post-options-trigger-'.$post->uid.'"', false)
            ->assertSee('aria-label="'.__('threads.actions.open_options').'"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee(':aria-expanded="openOptions"', false)
            ->assertSee('aria-controls="post-options-'.$post->uid.'"', false)
            ->assertSee('id="post-options-'.$post->uid.'"', false)
            ->assertSee('role="menu"', false)
            ->assertSee('aria-labelledby="post-options-trigger-'.$post->uid.'"', false)
            ->assertSee('role="menuitem"', false)
            ->assertSee('@keydown.escape.window="if (openOptions) closeOptions(true)"', false)
            ->assertSee(__('threads.actions.edit'))
            ->assertSee(__('threads.actions.delete'))
            ->assertDontSee('aria-label="'.__('threads.actions.edit').'"', false);
    }

    public function test_feed_header_options_stay_out_of_normal_flow_for_owner_and_repost_items(): void
    {
        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $other = User::factory()->create();
        $ownerPost = $this->createPost($owner, [
            'content' => 'Owner feed body',
        ]);
        $this->createPost($other, [
            'content' => 'Non-owner feed body',
        ]);
        $repost = $this->createPost($reposter, [
            'repost_id' => $ownerPost->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($owner)->get(route('feed'));
        $html = (string) $response->getContent();
        $css = file_get_contents(resource_path('css/app.css'));

        $response->assertOk()
            ->assertSee('Owner feed body')
            ->assertSee('Non-owner feed body')
            ->assertSee(__('threads.reposted_by', ['username' => $reposter->username]));
        $this->assertIsString($css);
        $this->assertSame(2, substr_count($html, 'class="skuy-post__options"'));
        $this->assertStringContainsString('id="post-options-trigger-'.$ownerPost->uid.'"', $html);
        $this->assertStringContainsString('id="post-options-trigger-'.$repost->uid.'"', $html);
        $this->assertStringNotContainsString('id="post-options-trigger-'.$other->uid.'"', $html);
        $this->assertMatchesRegularExpression(
            '/\.skuy-post__options\s*\{[^}]*position:\s*absolute;[^}]*top:\s*0;[^}]*right:\s*0;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-post__header:has\(\.skuy-post__options\)\s*\{[^}]*padding-right:\s*56px;/s',
            $css,
        );
    }

    public function test_feed_mobile_actions_preserve_equal_slots_and_touch_width(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.skuy-actions\s*\{[^}]*grid-template-columns:\s*repeat\(5,\s*minmax\(0,\s*1fr\)\)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-actions\s*>\s*button\s*\{[^}]*min-width:\s*44px/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-action\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;/s',
            $css,
        );

        $mobileStart = strpos($css, '@media (max-width: 767px)');
        $mobileEnd = strpos($css, '@media (min-width: 640px)', $mobileStart === false ? 0 : $mobileStart);

        $this->assertIsInt($mobileStart);
        $this->assertIsInt($mobileEnd);

        $mobileCss = substr($css, $mobileStart, $mobileEnd - $mobileStart);
        $this->assertMatchesRegularExpression(
            '/\.skuy-action--dense-count\s*\{\s*gap:\s*0;\s*font-size:\s*0\.625rem;\s*\}/s',
            $mobileCss,
        );
        $this->assertMatchesRegularExpression(
            '/\.skuy-action--dense-count\s+svg\s*,\s*\.skuy-action--dense-count\s+\.skuy-fa-icon\s*\{\s*width:\s*14px;\s*height:\s*14px;\s*\}/s',
            $mobileCss,
        );
    }

    public function test_feed_compacts_large_action_counts_without_losing_full_labels(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $cases = [
            [[999, 1000, 12345], ['999', '1K', '12K'], 0],
            [[123456, 999999, 1000000], ['123K', '999K', '1M'], 2],
            [[12345678, 123456789, 999999999], ['12M', '123M', '999M'], 2],
            [[1, 2, 10], ['1', '2', '10'], 0],
        ];

        foreach ($cases as [$counts, $visibleCounts, $denseCount]) {
            [$likeCount, $commentCount, $repostCount] = $counts;
            $post = $this->createPost($owner);

            $post->setRelation('user', $owner);
            $post->setRelation('feedComments', collect());
            $post->setAttribute('likes_count', $likeCount);
            $post->setAttribute('comments_count', $commentCount);
            $post->setAttribute('reposts_count', $repostCount);
            $post->setAttribute('root_comments_count', 0);
            $post->setAttribute('liked_by_me', false);
            $post->setAttribute('reposted_by_me', false);
            $post->setAttribute('bookmarked_by_me', false);

            $html = (string) $this->actingAs($viewer)
                ->view('partials.feed-threads', ['posts' => collect([$post])]);

            foreach ($visibleCounts as $visibleCount) {
                $this->assertStringContainsString('>'.$visibleCount.'</span>', $html);
            }

            $this->assertSame($denseCount, substr_count($html, 'skuy-action--dense-count'));
            $this->assertStringNotContainsString('>99K</span>', $html);
            $this->assertStringNotContainsString('>99M</span>', $html);

            foreach ([
                __('threads.labels.like') => $likeCount,
                __('threads.actions.reply') => $commentCount,
                __('threads.actions.repost') => $repostCount,
            ] as $label => $count) {
                $accessibleLabel = $label.' ('.$count.')';
                $this->assertStringContainsString('aria-label="'.$accessibleLabel.'"', $html);
                $this->assertStringContainsString('title="'.$accessibleLabel.'"', $html);
            }
        }
    }

    public function test_text_only_post_creation_still_succeeds(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Text-only composer post',
            'topic' => 'composer-topic',
        ]);

        $response->assertRedirect(route('feed'));
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Text-only composer post',
            'topic' => 'composer-topic',
        ]);
    }

    public function test_one_valid_image_post_is_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'One image composer post',
            'images' => [UploadedFile::fake()->image('one.jpg')],
        ]);

        $response->assertRedirect(route('feed'));
        $post = Post::query()->where('content', 'One image composer post')->firstOrFail();

        $this->assertCount(1, $post->images);
        Storage::disk('public')->assertExists($post->images[0]);
    }

    public function test_four_valid_images_are_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $images = [];

        for ($number = 1; $number <= 4; $number++) {
            $images[] = UploadedFile::fake()->image("image-{$number}.jpg");
        }

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Four image composer post',
            'images' => $images,
        ]);

        $response->assertRedirect(route('feed'));
        $post = Post::query()->where('content', 'Four image composer post')->firstOrFail();

        $this->assertCount(4, $post->images);
        foreach ($post->images as $imagePath) {
            Storage::disk('public')->assertExists($imagePath);
        }
    }

    public function test_more_than_four_images_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $images = [];

        for ($number = 1; $number <= 5; $number++) {
            $images[] = UploadedFile::fake()->image("too-many-{$number}.jpg");
        }

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Too many images composer post',
            'images' => $images,
        ]);

        $response->assertSessionHasErrors('images');
        $this->assertDatabaseMissing('posts', ['content' => 'Too many images composer post']);
    }

    public function test_invalid_image_mime_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Invalid image mime post',
            'images' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseMissing('posts', ['content' => 'Invalid image mime post']);
    }

    public function test_oversized_image_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'content' => 'Oversized image post',
            'images' => [UploadedFile::fake()->image('large.jpg')->size(5121)],
        ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertDatabaseMissing('posts', ['content' => 'Oversized image post']);
    }

    public function test_feed_exposes_bounded_unique_ordered_topic_suggestions(): void
    {
        $user = User::factory()->create();

        for ($number = 0; $number < 3; $number++) {
            $this->createPost($user, ['topic' => null]);
        }
        $this->createPost($user, ['topic' => '']);

        $topics = collect(['zeta', 'alpha', 'middle']);
        foreach ($topics as $topic) {
            for ($number = 0; $number < 2; $number++) {
                $this->createPost($user, ['topic' => $topic]);
            }
        }

        $response = $this->actingAs($user)->get(route('feed'));
        $availableTopics = $response->viewData('availableTopics');

        $response->assertOk();
        $this->assertSame(['alpha', 'middle', 'zeta'], $availableTopics->all());
        $this->assertCount(3, $availableTopics);
        $document = new DOMDocument();
        @$document->loadHTML((string) $response->getContent());
        $xpath = new DOMXPath($document);
        $topicComponentData = null;
        foreach ($xpath->query('//*[@x-data]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $candidate = $element->getAttribute('x-data');
            if (str_contains($candidate, 'feedTopicAutocomplete(')) {
                $topicComponentData = $candidate;
                break;
            }
        }
        $this->assertIsString($topicComponentData);
        $this->assertStringContainsString(
            'feedTopicAutocomplete',
            $topicComponentData,
        );
        $this->assertStringContainsString('alpha', $topicComponentData);
        $this->assertStringContainsString('middle', $topicComponentData);
        $this->assertStringContainsString('zeta', $topicComponentData);
    }

    public function test_feed_topic_suggestions_are_limited_to_twenty(): void
    {
        $user = User::factory()->create();

        for ($number = 1; $number <= 25; $number++) {
            $this->createPost($user, [
                'topic' => sprintf('topic-%02d', $number),
            ]);
        }

        $response = $this->actingAs($user)->get(route('feed'));
        $availableTopics = $response->viewData('availableTopics');

        $this->assertCount(20, $availableTopics);
        $this->assertSame('topic-01', $availableTopics->first());
        $this->assertSame('topic-20', $availableTopics->last());
        $this->assertNotContains('topic-21', $availableTopics->all());
    }

    public function test_feed_topic_combobox_exposes_an_in_flow_accessible_listbox_contract(): void
    {
        $viewer = User::factory()->create();
        $this->createPost($viewer, ['topic' => 'alpha']);
        $this->createPost($viewer, ['topic' => 'beta']);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $html = (string) $response->getContent();

        $response->assertOk()
            ->assertSee('x-data="feedTopicAutocomplete(', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('aria-autocomplete="list"', false)
            ->assertSee('aria-controls="composer-topic-suggestions"', false)
            ->assertSee(':aria-expanded="expanded.toString()"', false)
            ->assertSee(':aria-activedescendant="activeDescendant"', false)
            ->assertSee('id="composer-topic-suggestions"', false)
            ->assertSee('role="listbox"', false)
            ->assertSee('role="option"', false)
            ->assertSee('aria-selected', false)
            ->assertDontSee('absolute left-0 top-full z-50', false);

        $css = file_get_contents(resource_path('css/skuy-prism.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('max-height: clamp(180px, 30vh, 240px);', $css);
        $this->assertStringContainsString('overflow-y: auto;', $css);
    }

    public function test_feed_carousel_exposes_touch_scroll_keyboard_and_desktop_controls(): void
    {
        $author = User::factory()->create();
        $this->createPost($author, [
            'content' => 'Three-image accessible carousel',
            'images' => ['gallery-one.jpg', 'gallery-two.jpg', 'gallery-three.jpg'],
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('feed'));
        $html = (string) $response->getContent();

        $response->assertOk()
            ->assertSee('role="region"', false)
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertSee('data-feed-media-previous', false)
            ->assertSee('data-feed-media-next', false)
            ->assertSee('role="status" aria-live="polite"', false)
            ->assertSee('@keydown.arrow-left.prevent.stop="moveSlide(-1)"', false)
            ->assertSee('@keydown.arrow-right.prevent.stop="moveSlide(1)"', false)
            ->assertSee('data-media-slide="true"', false)
            ->assertSee('@click.stop="openLightbox(', false);

        $this->assertSame(3, substr_count($html, 'data-media-slide="true"'));
        $this->assertSame(3, substr_count($html, 'data-media-indicator-dot="true"'));
        $this->assertStringContainsString('overflow-x: auto;', file_get_contents(resource_path('css/skuy-prism.css')));
    }

    public function test_feed_badge_uses_public_verification_for_original_author_not_email_or_reposter(): void
    {
        $verifiedAuthor = User::factory()->create(['username' => 'public.verified']);
        $verifiedAuthor->forceFill(['is_verified' => true, 'email_verified_at' => null])->save();
        $reposter = User::factory()->create(['username' => 'plain.reposter']);
        $reposter->forceFill(['is_verified' => false])->save();
        $emailOnlyAuthor = User::factory()->create(['username' => 'email.only']);
        $emailOnlyAuthor->forceFill(['is_verified' => false, 'email_verified_at' => now()])->save();

        $original = $this->createPost($verifiedAuthor, ['content' => 'Verified original author badge']);
        $repost = $this->createPost($reposter, [
            'repost_id' => $original->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);
        $emailOnlyPost = $this->createPost($emailOnlyAuthor, ['content' => 'Email-only author is not public-verified']);

        $response = $this->actingAs(User::factory()->create())->get(route('feed'));
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        @$document->loadHTML((string) $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);

        foreach ([$original, $repost] as $feedItem) {
            $article = $xpath->query('//article[@data-feed-thread-key="'.$feedItem->uid.'"]')->item(0);
            $this->assertNotNull($article);
            $this->assertSame(1, $xpath->query('.//*[@data-verified-badge]', $article)->length);
        }

        $emailOnlyArticle = $xpath->query('//article[@data-feed-thread-key="'.$emailOnlyPost->uid.'"]')->item(0);
        $this->assertNotNull($emailOnlyArticle);
        $this->assertSame(0, $xpath->query('.//*[@data-verified-badge]', $emailOnlyArticle)->length);
        $response->assertSee(__('threads.reposted_by', ['username' => $reposter->username]));
    }

    public function test_feed_aurora_is_viewport_anchored_and_context_rail_is_height_bounded(): void
    {
        $css = file_get_contents(resource_path('css/skuy-prism.css'));
        $this->assertIsString($css);

        foreach ([
            '.skuy-app-shell::before',
            'position: fixed;',
            'pointer-events: none;',
            'radial-gradient(',
            '@media (min-width: 1200px)',
            'position: sticky;',
            'max-height: calc(100vh - var(--skuy-feed-context-sticky-offset) - var(--skuy-feed-context-bottom-reserve));',
            'max-height: max(0px, calc(100dvh - var(--skuy-feed-context-sticky-offset) - var(--skuy-feed-context-bottom-reserve)));',
            '--skuy-feed-context-bottom-gap: 16px;',
            '--skuy-feed-shell-bottom-padding: 56px;',
            '--skuy-feed-context-bottom-reserve: calc(var(--skuy-feed-context-bottom-gap) + var(--skuy-feed-shell-bottom-padding));',
            'padding-bottom: var(--skuy-feed-shell-bottom-padding);',
            'max-height: max(0px, calc(100dvh - var(--skuy-feed-context-menu-open-offset) - var(--skuy-feed-context-bottom-reserve)));',
            'overflow-y: auto;',
            '@media (prefers-reduced-transparency: reduce)',
        ] as $contract) {
            $this->assertTrue(str_contains($css, $contract), "Missing feed layout/ambience contract: {$contract}");
        }
    }

    public function test_feed_orders_posts_by_created_at_then_id_descending(): void
    {
        $user = User::factory()->create();
        $sameTime = Carbon::create(2026, 8, 31, 12, 0, 0);

        $older = $this->createPost($user, [
            'created_at' => $sameTime->copy()->subMinute(),
            'updated_at' => $sameTime->copy()->subMinute(),
        ]);
        $tieLower = $this->createPost($user, [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);
        $tieHigher = $this->createPost($user, [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);

        $response = $this->actingAs($user)->get(route('feed'));
        $ids = $response->viewData('posts')->getCollection()->pluck('id')->all();

        $this->assertSame([$tieHigher->id, $tieLower->id, $older->id], $ids);
    }

    public function test_feed_uses_ten_item_cursor_batches_and_a_server_rendered_load_more_link(): void
    {
        $user = User::factory()->create(['locale' => 'id']);
        $baseTime = Carbon::create(2026, 8, 31, 12, 0, 0);

        $posts = collect();

        for ($number = 1; $number <= 11; $number++) {
            $posts->push($this->createPost($user, [
                'content' => "Feed post {$number}",
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]));
        }

        $pageOne = $this->actingAs($user)->get(route('feed'));
        $pageOnePosts = $pageOne->viewData('posts');

        $this->assertInstanceOf(CursorPaginator::class, $pageOnePosts);
        $this->assertCount(10, $pageOnePosts->getCollection());
        $this->assertSame(
            $posts->reverse()->take(10)->pluck('id')->all(),
            $pageOnePosts->getCollection()->pluck('id')->all(),
        );
        $this->assertTrue($pageOnePosts->hasMorePages());
        $pageOne
            ->assertSee('Lihat Utas Lainnya')
            ->assertSee('id="feed-thread-list"', false)
            ->assertSee('@click="loadMoreThreads($event)"', false)
            ->assertSee(':aria-disabled="isLoadingMore.toString()"', false)
            ->assertSee(':aria-busy="isLoadingMore.toString()"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('min-h-[44px]', false)
            ->assertDontSee('if (this.feedLoadFailed) return;', false)
            ->assertSee('if (this.isLoadingMore || !this.nextFeedUrl) return;', false)
            ->assertSee('this.feedLoadFailed = false;', false)
            ->assertSee('this.feedLoadFailed = true;', false)
            ->assertSee("'X-Feed-Fragment': '1'", false)
            ->assertSee("this.nextFeedUrl = batch.has_more ? batch.next_url : '';", false)
            ->assertSee('window.copyAndToast', false)
            ->assertSee('skuy-load-more', false)
            ->assertDontSee('history.pushState', false)
            ->assertDontSee('history.replaceState', false);

        $nextPageUrl = $pageOnePosts->nextPageUrl();

        $this->assertNotNull($nextPageUrl);
        $this->assertStringContainsString('cursor=', $nextPageUrl);
        $this->assertStringNotContainsString('page=', $nextPageUrl);
        $pageOne
            ->assertSee('href="'.e($nextPageUrl).'"', false)
            ->assertSee('data-feed-next-url="'.e($nextPageUrl).'"', false);

        $pageTwo = $this->actingAs($user)->get($nextPageUrl);
        $pageTwoPosts = $pageTwo->viewData('posts');

        $this->assertCount(1, $pageTwoPosts->getCollection());
        $this->assertSame($posts->first()->id, $pageTwoPosts->getCollection()->first()->id);
        $this->assertSame([], array_values(array_intersect(
            $pageOnePosts->getCollection()->pluck('id')->all(),
            $pageTwoPosts->getCollection()->pluck('id')->all(),
        )));
        $this->assertFalse($pageTwoPosts->hasMorePages());
        $pageTwo->assertDontSee('Lihat Utas Lainnya');
    }

    public function test_feed_does_not_render_load_more_when_ten_threads_exist(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        for ($number = 1; $number <= 10; $number++) {
            $this->createPost($user, ['content' => "Bounded feed post {$number}"]);
        }

        $response = $this->actingAs($user)->get(route('feed'));

        $this->assertCount(10, $response->viewData('posts')->getCollection());
        $this->assertFalse($response->viewData('posts')->hasMorePages());
        $response->assertDontSee('Lihat Utas Lainnya');
    }

    public function test_enhanced_feed_returns_bounded_server_rendered_cursor_batches_without_duplicates(): void
    {
        $user = User::factory()->create();
        $baseTime = Carbon::create(2026, 8, 31, 12, 0, 0);
        $posts = collect();

        for ($number = 1; $number <= 21; $number++) {
            $posts->push($this->createPost($user, [
                'content' => "Enhanced feed post {$number}",
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]));
        }

        $pageOne = $this->actingAs($user)->get(route('feed'));
        $pageOnePosts = $pageOne->viewData('posts');
        $expectedKeys = $posts->reverse()->values()->pluck('uid');
        $initialKeys = $pageOnePosts->getCollection()->pluck('uid')->all();
        $nextPageUrl = $pageOnePosts->nextPageUrl();

        $this->assertCount(10, $initialKeys);
        $this->assertSame($expectedKeys->take(10)->all(), $initialKeys);
        $this->assertNotNull($nextPageUrl);

        $pageTwo = $this->withHeader('X-Feed-Fragment', '1')->get($nextPageUrl);

        $pageTwo
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Vary', 'X-Feed-Fragment')
            ->assertJsonStructure(['html', 'has_more', 'next_url'])
            ->assertJsonPath('has_more', true);

        $pageTwoHtml = $pageTwo->json('html');
        $pageThreeUrl = $pageTwo->json('next_url');

        $this->assertIsString($pageTwoHtml);
        $pageTwoKeys = $this->feedThreadKeysFromHtml($pageTwoHtml);
        $this->assertCount(10, $pageTwoKeys);
        $this->assertSame($expectedKeys->slice(10, 10)->values()->all(), $pageTwoKeys);
        $this->assertSame([], array_values(array_intersect($initialKeys, $pageTwoKeys)));
        $this->assertStringNotContainsString('name="images[]"', $pageTwoHtml);
        $this->assertIsString($pageThreeUrl);
        $this->assertStringContainsString('cursor=', $pageThreeUrl);
        $this->assertStringNotContainsString('page=', $pageThreeUrl);

        $pageThree = $this->withHeader('X-Feed-Fragment', '1')->get($pageThreeUrl);

        $pageThree
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_url', null);

        $pageThreeHtml = $pageThree->json('html');
        $this->assertIsString($pageThreeHtml);
        $pageThreeKeys = $this->feedThreadKeysFromHtml($pageThreeHtml);

        $this->assertSame($expectedKeys->slice(20)->values()->all(), $pageThreeKeys);
        $this->assertSame([], array_values(array_intersect(
            array_merge($initialKeys, $pageTwoKeys),
            $pageThreeKeys,
        )));
    }

    public function test_enhanced_feed_rejects_unauthenticated_and_invalid_requests_without_rendering_threads(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $protectedContent = 'This thread must not appear in an invalid fragment response';

        $this->createPost($author, ['content' => $protectedContent]);

        $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Feed-Fragment' => '1',
            ])
            ->get(route('feed', ['cursor' => 'not-a-valid-cursor']))
            ->assertUnauthorized()
            ->assertDontSee($protectedContent);

        $invalidCursors = [
            'not-a-valid-cursor',
            (new Cursor(['created_at' => now()->toDateTimeString()], true))->encode(),
        ];

        foreach ($invalidCursors as $cursor) {
            $this
                ->actingAs($viewer)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Feed-Fragment' => '1',
                ])
                ->get(route('feed', ['cursor' => $cursor]))
                ->assertStatus(422)
                ->assertDontSee($protectedContent);
        }
    }

    public function test_feed_cursor_url_does_not_preserve_thread_detail_query_state(): void
    {
        $user = User::factory()->create();

        for ($number = 1; $number <= 11; $number++) {
            $this->createPost($user, ['content' => "Query-state feed post {$number}"]);
        }

        $response = $this->actingAs($user)->get(route('feed', [
            'page' => 4,
            'comments_page' => 2,
            'replies_for' => 123,
            'replies_cursor' => 'stale-reply-cursor',
        ]));
        $nextPageUrl = $response->viewData('posts')->nextPageUrl();

        $response->assertOk();
        $this->assertNotNull($nextPageUrl);

        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $nextPageQuery);

        $this->assertSame(['cursor'], array_keys($nextPageQuery));
        $this->actingAs($user)->get($nextPageUrl)->assertOk();
    }

    public function test_feed_cursor_uses_id_to_order_equal_timestamps_without_duplicates(): void
    {
        $user = User::factory()->create();
        $sameTime = Carbon::create(2026, 8, 31, 12, 0, 0);
        $posts = collect();

        for ($number = 1; $number <= 12; $number++) {
            $posts->push($this->createPost($user, [
                'content' => "Equal timestamp post {$number}",
                'created_at' => $sameTime,
                'updated_at' => $sameTime,
            ]));
        }

        $pageOne = $this->actingAs($user)->get(route('feed'))->viewData('posts');
        $pageTwo = $this
            ->withHeader('X-Feed-Fragment', '1')
            ->get($pageOne->nextPageUrl());
        $pageOneKeys = $pageOne->getCollection()->pluck('uid')->all();

        $pageTwo->assertOk();
        $pageTwoHtml = $pageTwo->json('html');
        $this->assertIsString($pageTwoHtml);
        $pageTwoKeys = $this->feedThreadKeysFromHtml($pageTwoHtml);
        $this->assertSame($posts->reverse()->take(10)->pluck('uid')->all(), $pageOneKeys);
        $this->assertSame($posts->take(2)->reverse()->values()->pluck('uid')->all(), $pageTwoKeys);
        $this->assertSame([], array_values(array_intersect($pageOneKeys, $pageTwoKeys)));
    }

    public function test_newer_post_inserted_between_cursor_requests_does_not_duplicate_seen_posts(): void
    {
        $user = User::factory()->create();
        $baseTime = Carbon::create(2026, 8, 31, 12, 0, 0);
        $posts = collect();

        for ($number = 1; $number <= 12; $number++) {
            $posts->push($this->createPost($user, [
                'content' => "Mutable feed post {$number}",
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]));
        }

        $pageOne = $this->actingAs($user)->get(route('feed'))->viewData('posts');
        $pageOneIds = $pageOne->getCollection()->pluck('id')->all();
        $nextPageUrl = $pageOne->nextPageUrl();
        $newPost = $this->createPost($user, [
            'content' => 'Inserted after the first batch',
            'created_at' => $baseTime->copy()->addMinute(),
            'updated_at' => $baseTime->copy()->addMinute(),
        ]);

        $pageTwo = $this->actingAs($user)->get($nextPageUrl)->viewData('posts');
        $pageTwoIds = $pageTwo->getCollection()->pluck('id')->all();

        $this->assertSame($posts->take(2)->reverse()->values()->pluck('id')->all(), $pageTwoIds);
        $this->assertNotContains($newPost->id, $pageTwoIds);
        $this->assertSame([], array_values(array_intersect($pageOneIds, $pageTwoIds)));
    }

    public function test_feed_load_more_action_is_localized_for_supported_account_locales(): void
    {
        $author = User::factory()->create();

        for ($number = 1; $number <= 11; $number++) {
            $this->createPost($author, ['content' => "Localized feed post {$number}"]);
        }

        foreach ([
            'id' => [
                'Lihat Utas Lainnya',
                'Memuat Utas...',
                'Utas berikutnya tidak dapat dimuat. Pilih tautan lagi untuk melanjutkan.',
            ],
            'en' => [
                'View More Threads',
                'Loading Threads...',
                'Could not load more Threads. Select the link again to continue.',
            ],
        ] as $locale => $copy) {
            $viewer = User::factory()->create(['locale' => $locale]);

            $response = $this->actingAs($viewer)
                ->get(route('feed'))
                ->assertOk();

            foreach ($copy as $text) {
                $response->assertSee($text);
            }
        }
    }

    public function test_repost_remains_a_feed_item_and_displays_original_content_and_author(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, [
            'content' => 'The original feed content',
        ]);
        $repost = $this->createPost($actor, [
            'repost_id' => $original->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($actor)->get(route('feed'));

        $response->assertOk()
            ->assertSee('The original feed content')
            ->assertSee($owner->username)
            ->assertSee($actor->username);

        $this->assertTrue(
            $response->viewData('posts')->getCollection()->contains('id', $repost->id)
        );
    }

    public function test_feed_exposes_counts_interaction_state_and_ownership_without_loading_collection_helpers(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->createPost($owner);

        Like::create(['post_id' => $post->id, 'user_id' => $viewer->id]);
        Like::create(['post_id' => $post->id, 'user_id' => $other->id]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $other->id,
            'content' => 'A root comment',
            'parent_id' => null,
        ]);
        Comment::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
            'content' => 'A reply',
            'parent_id' => $comment->id,
        ]);

        $this->createPost($viewer, [
            'repost_id' => $post->id,
            'content' => null,
        ]);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $displayPost = $response->viewData('posts')->getCollection()->first(
            fn (Post $feedPost): bool => $feedPost->id === $post->id
        );

        $this->assertNotNull($displayPost);
        $this->assertSame(2, $displayPost->likes_count);
        $this->assertSame(2, $displayPost->comments_count);
        $this->assertSame(1, $displayPost->root_comments_count);
        $this->assertSame(1, $displayPost->reposts_count);
        $this->assertTrue((bool) $displayPost->liked_by_me);
        $this->assertTrue((bool) $displayPost->reposted_by_me);
        $this->assertSame($owner->id, $displayPost->user_id);
    }

    public function test_feed_uses_post_uid_and_user_username_in_public_links(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($viewer)->get(route('feed'));

        $this->assertSame('uid', $post->getRouteKeyName());
        $this->assertSame('username', $owner->getRouteKeyName());
        $response->assertSee(route('posts.show', $post), false);
        $response->assertSee(route('user.profile', $owner), false);
    }

    public function test_feed_owner_sees_edit_link_for_owned_post(): void
    {
        $owner = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($owner)->get(route('feed'));

        $response->assertSee(route('posts.edit', $post), false);
    }

    public function test_feed_uses_a_bounded_comment_preview_and_keeps_the_full_root_count(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $baseTime = Carbon::create(2026, 8, 31, 12, 0, 0);

        for ($number = 1; $number <= 4; $number++) {
            $this->createComment($post, $author, "Root comment {$number}", [
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('feed'));
        $feedPost = $response->viewData('posts')->getCollection()->firstWhere('id', $post->id);

        $this->assertSame(4, $feedPost->root_comments_count);
        $this->assertCount(3, $feedPost->feedComments);
        $response->assertSee('Root comment 4')
            ->assertDontSee('Root comment 1');
    }

    public function test_feed_reply_preview_is_bounded_and_eager_loads_each_reply_user(): void
    {
        $author = User::factory()->create();
        $replyAuthors = User::factory()->count(3)->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Preview root comment');

        foreach ($replyAuthors as $index => $replyAuthor) {
            $number = $index + 1;
            $this->createComment($post, $replyAuthor, "Reply preview {$number}", [
                'parent_id' => $root->id,
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);
        }

        $response = $this->actingAs($viewer)->get(route('feed'));
        $feedPost = $response->viewData('posts')->getCollection()->firstWhere('id', $post->id);
        $previewRoot = $feedPost->feedComments->first();

        $this->assertCount(2, $previewRoot->feedReplies);
        foreach ($previewRoot->feedReplies as $reply) {
            $this->assertTrue($reply->relationLoaded('user'));
        }

        $response->assertSee('Reply preview 3')
            ->assertSee($replyAuthors[2]->username)
            ->assertDontSee('Reply preview 1');
    }

    public function test_comments_and_replies_outside_feed_preview_remain_available_on_post_detail(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Post with complete comments']);
        $outsideRoot = $this->createComment($post, $author, 'Older root available on detail');
        $outsideReplyAuthor = User::factory()->create();
        $outsideReply = $this->createComment($post, $outsideReplyAuthor, 'Older reply available on detail', [
            'parent_id' => $outsideRoot->id,
        ]);

        for ($number = 1; $number <= 3; $number++) {
            $root = $this->createComment($post, $author, "Preview root {$number}", [
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);

            $this->createComment($post, $author, "Preview reply {$number}", [
                'parent_id' => $root->id,
            ]);
        }

        $feedResponse = $this->actingAs($viewer)->get(route('feed'));
        $feedResponse->assertDontSee($outsideRoot->content)
            ->assertDontSee($outsideReply->content);

        $detailResponse = $this->actingAs($viewer)->get(route('posts.show', $post));
        $detailResponse->assertOk()
            ->assertSee($outsideRoot->content)
            ->assertSee($outsideReply->content)
            ->assertSee($outsideReplyAuthor->username);
    }

    public function test_repost_feed_item_uses_bounded_original_post_comments(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $original = $this->createPost($owner, ['content' => 'Original post with preview']);

        for ($number = 1; $number <= 4; $number++) {
            $this->createComment($original, $owner, "Original comment {$number}", [
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);
        }

        $repost = $this->createPost($actor, [
            'repost_id' => $original->id,
            'content' => null,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        $response = $this->actingAs($actor)->get(route('feed'));
        $feedRepost = $response->viewData('posts')->getCollection()->firstWhere('id', $repost->id);

        $this->assertTrue($feedRepost->originalPost->relationLoaded('feedComments'));
        $this->assertFalse($feedRepost->originalPost->relationLoaded('comments'));
        $this->assertSame(4, $feedRepost->originalPost->root_comments_count);
        $this->assertCount(3, $feedRepost->originalPost->feedComments);
        $response->assertSee($original->content)
            ->assertSee('Original comment 4')
            ->assertDontSee('Original comment 1');
    }

    public function test_feed_comment_and_reply_user_queries_do_not_grow_per_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Single post root');
        $this->createComment($post, $viewer, 'Single post reply', ['parent_id' => $root->id]);

        $onePostQueryCount = $this->measureFeedQueryCount($viewer);

        for ($number = 1; $number <= 2; $number++) {
            $additionalPost = $this->createPost($author, ['content' => "Additional post {$number}"]);
            $additionalRoot = $this->createComment($additionalPost, $author, "Additional root {$number}");
            $this->createComment($additionalPost, $viewer, "Additional reply {$number}", [
                'parent_id' => $additionalRoot->id,
            ]);
        }

        $threePostQueryCount = $this->measureFeedQueryCount($viewer);

        $this->assertSame($onePostQueryCount, $threePostQueryCount);
    }

    private function feedThreadKeysFromHtml(string $html): array
    {
        preg_match_all('/data-feed-thread-key="([^"]+)"/', $html, $matches);

        return $matches[1];
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        $post = Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'A feed post',
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

    private function createComment(Post $post, User $user, string $content, array $attributes = []): Comment
    {
        $comment = Comment::create(array_merge([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => null,
        ], $attributes));

        $timestamps = array_intersect_key($attributes, [
            'created_at' => true,
            'updated_at' => true,
        ]);

        if ($timestamps !== []) {
            $comment->forceFill($timestamps)->saveQuietly();
        }

        return $comment;
    }

    private function measureFeedQueryCount(User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($viewer)->get(route('feed'));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queryCount;
    }
}
