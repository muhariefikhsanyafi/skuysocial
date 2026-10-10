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
        $this->assertIsString($css);

        foreach ([
            'html[data-flow-page="feed"]',
            '--flow-canvas: #ffffff;',
            '--flow-canvas: #0d0e13;',
            '--flow-accent: #6e5bef;',
            'prefers-reduced-motion: reduce',
            'prefers-reduced-transparency: reduce',
            'grid-template-columns: repeat(5, minmax(0, 1fr));',
            'env(safe-area-inset-bottom',
            'min-width: 44px;',
            'min-height: 44px;',
        ] as $contract) {
            $this->assertStringContainsString($contract, $css, "Missing Flow feed styling contract: {$contract}");
        }

        $this->assertStringNotContainsString('radial-gradient(', $css);
        $this->assertStringNotContainsString('linear-gradient(', $css);
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
