<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class MediaRenderingParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep existing Blade markup parity assertions on the reversible fallback.
        config(['frontend.post_detail_inertia' => false]);
    }

    public function test_modern_single_image_renders_on_profile_hashtag_and_topic_listings(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'media-parity']);
        $post = $this->createPost($user, [
            'images' => ['posts/path-a.jpg'],
            'topic' => 'media-topic',
        ]);
        $post->hashtags()->attach($hashtag->id);

        $this->assertMediaRenderedOnListings($user, $hashtag, $post, ['posts/path-a.jpg']);
    }

    public function test_modern_multiple_images_render_on_profile_hashtag_and_topic_listings(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'multi-media']);
        $images = ['posts/path-a.jpg', 'posts/path-b.jpg', 'posts/path-c.jpg'];
        $post = $this->createPost($user, [
            'images' => $images,
            'topic' => 'multi-topic',
        ]);
        $post->hashtags()->attach($hashtag->id);

        $this->assertMediaRenderedOnListings($user, $hashtag, $post, $images, true);
    }

    public function test_legacy_image_renders_when_modern_images_are_empty(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'legacy-media']);
        $post = $this->createPost($user, [
            'images' => null,
            'image' => 'posts/legacy.jpg',
            'topic' => 'legacy-topic',
        ]);
        $post->hashtags()->attach($hashtag->id);

        $this->assertMediaRenderedOnListings($user, $hashtag, $post, ['posts/legacy.jpg']);
    }

    public function test_media_paths_are_unique_and_modern_images_win_over_legacy_image(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, [
            'images' => ['posts/shared.jpg', '', null, 'posts/second.jpg', 'posts/shared.jpg'],
            'image' => 'posts/legacy.jpg',
        ]);

        $response = $this->actingAs($user)->get(route('user.profile', $user));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, substr_count($content, asset('storage/posts/shared.jpg')));
        $this->assertSame(1, substr_count($content, asset('storage/posts/second.jpg')));
        $this->assertSame(1, substr_count($content, 'data-media-slider="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-slide="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-indicator-dot="true"'));
        $this->assertStringContainsString('@scroll.passive="updateDots"', $content);
        $this->assertStringNotContainsString(asset('storage/posts/legacy.jpg'), $content);
        $this->assertSame($post->uid, $post->getRouteKey());
    }

    public function test_repost_listing_renders_media_from_original_post(): void
    {
        $originalAuthor = User::factory()->create();
        $reposter = User::factory()->create();
        $original = $this->createPost($originalAuthor, [
            'images' => ['posts/original-a.jpg', 'posts/original-b.jpg'],
        ]);
        $repost = $this->createPost($reposter, [
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $response = $this->actingAs($reposter)->get(route('user.profile', $reposter));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee(asset('storage/posts/original-a.jpg'), false)
            ->assertSee(asset('storage/posts/original-b.jpg'), false);
        $this->assertSame(1, substr_count($content, 'data-media-slider="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-slide="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-indicator-dot="true"'));
        $this->assertSame($repost->id, $response->viewData('posts')->first()->id);
    }

    public function test_listing_without_media_renders_without_post_media_markup(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);

        $response = $this->actingAs($user)->get(route('user.profile', $user));

        $response->assertOk();
        $this->assertStringNotContainsString('storage/posts/', $response->getContent());
        $this->assertStringNotContainsString('data-media-indicator', $response->getContent());
        $this->assertSame($post->uid, $post->getRouteKey());
        $response->assertSee(route('posts.show', $post), false);
    }

    public function test_post_detail_single_image_renders_without_slider_or_indicator(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, ['images' => ['posts/detail-single.jpg']]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee(asset('storage/posts/detail-single.jpg'), false);
        $this->assertSame(0, substr_count($content, 'data-media-slider="true"'));
        $this->assertSame(0, substr_count($content, 'data-media-slide="true"'));
        $this->assertStringNotContainsString('data-media-indicator', $content);
    }

    public function test_post_detail_multiple_images_use_slider_and_indicator_state(): void
    {
        $user = User::factory()->create();
        $images = ['posts/detail-a.jpg', 'posts/detail-b.jpg', 'posts/detail-c.jpg'];
        $post = $this->createPost($user, ['images' => $images]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, substr_count($content, 'data-media-slider="true"'));
        $this->assertSame(count($images), substr_count($content, 'data-media-slide="true"'));
        $this->assertSame(count($images), substr_count($content, 'data-media-indicator-dot="true"'));
        $this->assertStringContainsString('activeSlide: 0', $content);
        $this->assertStringContainsString('@scroll.passive="updateDots"', $content);
        $this->assertStringContainsString('class="skuy-media-track"', $content);
        $this->assertStringContainsString('activeSlide === 0', $content);
        $this->assertStringContainsString('activeSlide === 2', $content);
        $this->assertStringContainsString('class="skuy-media-dot"', $content);
    }

    public function test_post_detail_without_media_has_no_slider_or_indicator(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user);

        $response = $this->actingAs($user)->get(route('posts.show', $post));
        $content = $response->getContent();

        $response->assertOk();
        $this->assertStringNotContainsString('data-media-slider', $content);
        $this->assertStringNotContainsString('data-media-slide', $content);
        $this->assertStringNotContainsString('data-media-indicator', $content);
    }

    public function test_post_detail_repost_slider_uses_original_media(): void
    {
        $originalAuthor = User::factory()->create();
        $reposter = User::factory()->create();
        $original = $this->createPost($originalAuthor, [
            'images' => ['posts/detail-original-a.jpg', 'posts/detail-original-b.jpg'],
        ]);
        $repost = $this->createPost($reposter, [
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $response = $this->actingAs($reposter)->get(route('posts.show', $repost));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee(asset('storage/posts/detail-original-a.jpg'), false)
            ->assertSee(asset('storage/posts/detail-original-b.jpg'), false);
        $this->assertSame(1, substr_count($content, 'data-media-slider="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-slide="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-indicator-dot="true"'));
        $this->assertSame($original->id, $response->viewData('displayPost')->id);
    }

    public function test_post_detail_normalizes_invalid_and_duplicate_media_paths(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, [
            'images' => ['posts/detail-shared.jpg', '', null, 'posts/detail-second.jpg', 'posts/detail-shared.jpg'],
            'image' => 'posts/detail-legacy.jpg',
        ]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee(asset('storage/posts/detail-shared.jpg'), false)
            ->assertSee(asset('storage/posts/detail-second.jpg'), false);
        $this->assertSame(2, substr_count($content, 'data-media-slide="true"'));
        $this->assertSame(2, substr_count($content, 'data-media-indicator-dot="true"'));
        $this->assertStringNotContainsString(asset('storage/posts/detail-legacy.jpg'), $content);
    }

    public function test_post_detail_uses_legacy_image_when_modern_media_is_empty(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, [
            'images' => null,
            'image' => 'posts/detail-legacy.jpg',
        ]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee(asset('storage/posts/detail-legacy.jpg'), false);
        $this->assertSame(0, substr_count($content, 'data-media-slider="true"'));
        $this->assertStringNotContainsString('data-media-indicator', $content);
    }

    public function test_media_listings_keep_fifteen_item_pagination_and_post_uid_links(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::create(['name' => 'paged-media']);

        for ($number = 1; $number <= 16; $number++) {
            $post = $this->createPost($user, [
                'topic' => 'paged-topic',
                'images' => ["posts/page-{$number}.jpg"],
            ]);
            $post->hashtags()->attach($hashtag->id);
        }

        $profilePosts = $this->actingAs($user)
            ->get(route('user.profile', $user))
            ->viewData('posts');
        $hashtagResponse = $this->actingAs($user)->get(route('tags.show', $hashtag->name));
        $topicResponse = $this->actingAs($user)->get(route('topik.show', 'paged-topic'));

        $hashtagPosts = $hashtagResponse->viewData('posts');
        $topicPosts = $topicResponse->viewData('posts');

        $this->assertInstanceOf(Paginator::class, $profilePosts);
        $this->assertSame(15, $profilePosts->perPage());
        $this->assertSame(15, $hashtagPosts->perPage());
        $this->assertSame(15, $topicPosts->perPage());
        $this->assertTrue($hashtagPosts->hasMorePages());
        $this->assertTrue($topicPosts->hasMorePages());
        $this->assertSame('uid', Post::first()->getRouteKeyName());
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Media parity post',
        ], $attributes));
    }

    private function assertMediaRenderedOnListings(
        User $user,
        Hashtag $hashtag,
        Post $post,
        array $mediaPaths,
        bool $expectIndicator = false,
    ): void {
        $urls = [
            route('user.profile', $user),
            route('tags.show', $hashtag->name),
            route('topik.show', $post->topic),
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($user)->get($url);
            $content = $response->getContent();

            $response->assertOk()
                ->assertSee(route('posts.show', $post), false);

            foreach ($mediaPaths as $mediaPath) {
                $response->assertSee(asset('storage/'.$mediaPath), false);
            }

            if ($expectIndicator) {
                $this->assertSame(1, substr_count($content, 'data-media-slider="true"'));
                $this->assertSame(count($mediaPaths), substr_count($content, 'data-media-slide="true"'));
                $this->assertSame(count($mediaPaths), substr_count($content, 'data-media-indicator-dot="true"'));
                $this->assertStringContainsString('activeSlide: 0', $content);
                $this->assertStringContainsString('@scroll.passive="updateDots"', $content);
                $this->assertStringContainsString('overflow-x-auto snap-x snap-mandatory', $content);
                $this->assertStringContainsString('activeSlide === 0', $content);
                $this->assertStringContainsString('activeSlide === '.(count($mediaPaths) - 1), $content);
                $this->assertStringContainsString('w-1.5 sm:w-2 bg-gray-300 dark:bg-[#242424]', $content);
            } else {
                $this->assertSame(0, substr_count($content, 'data-media-slider="true"'));
                $this->assertSame(0, substr_count($content, 'data-media-slide="true"'));
                $this->assertSame(0, substr_count($content, 'data-media-indicator-dot="true"'));
            }
        }
    }
}
