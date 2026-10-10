<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class ProfileTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_defaults_to_threads_tab(): void
    {
        $user = User::factory()->create();
        $this->createPost($user, 'Thread content');

        $response = $this->actingAs($user)->get(route('user.profile', $user));

        $response->assertOk()
            ->assertViewHas('activeTab', 'threads')
            ->assertSee('data-profile-tab="threads"', false)
            ->assertDontSee('data-profile-tab="likes"', false)
            ->assertSee('data-account-menu-key="likes"', false)
            ->assertSee(route('user.profile', ['username' => $user->username, 'tab' => 'likes']), false);
    }

    public function test_explicit_threads_tab_uses_threads_query_and_active_state(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost($user, 'Thread tab content');

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=threads');

        $response->assertOk()->assertViewHas('activeTab', 'threads')->assertSee($post->content);
    }

    public function test_invalid_tab_falls_back_to_threads(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=invalid');

        $response->assertOk()->assertViewHas('activeTab', 'threads');
    }

    public function test_likes_tab_is_active_and_links_back_to_profile_tabs(): void
    {
        $user = User::factory()->create();
        $likedPost = $this->createPost(User::factory()->create(), 'Liked content');
        $this->like($user, $likedPost);

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=likes');

        $response->assertOk()->assertViewHas('activeTab', 'likes')->assertSee($likedPost->content);
        $response->assertSee(route('user.profile', $user), false);
        $response->assertSee(route('user.profile', ['username' => $user->username, 'tab' => 'likes']), false);
    }

    public function test_likes_tab_uses_profile_owner_not_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $ownerLikedPost = $this->createPost(User::factory()->create(), 'Owner liked post');
        $viewerLikedPost = $this->createPost(User::factory()->create(), 'Viewer liked post');
        $this->like($owner, $ownerLikedPost);
        $this->like($viewer, $viewerLikedPost);

        $response = $this->actingAs($viewer)->get(route('user.profile', $owner).'?tab=likes');

        $response->assertOk()->assertSee($ownerLikedPost->content)->assertDontSee($viewerLikedPost->content);
    }

    public function test_liked_posts_are_ordered_by_like_timestamp_descending(): void
    {
        $user = User::factory()->create();
        $older = $this->createPost(User::factory()->create(), 'Older like');
        $newer = $this->createPost(User::factory()->create(), 'Newer like');
        $this->like($user, $older, '2026-08-31 10:00:00');
        $this->like($user, $newer, '2026-08-31 11:00:00');

        $posts = $this->getLikesPage($user)->getCollection();

        $this->assertSame([$newer->id, $older->id], $posts->pluck('id')->all());
    }

    public function test_liked_posts_use_like_id_as_deterministic_tie_breaker(): void
    {
        $user = User::factory()->create();
        $firstPost = $this->createPost(User::factory()->create(), 'First tied like');
        $secondPost = $this->createPost(User::factory()->create(), 'Second tied like');
        $timestamp = '2026-08-31 12:00:00';
        $firstLike = $this->like($user, $firstPost, $timestamp);
        $secondLike = $this->like($user, $secondPost, $timestamp);

        $posts = $this->getLikesPage($user)->getCollection();

        $this->assertSame([$secondPost->id, $firstPost->id], $posts->pluck('id')->all());
        $this->assertGreaterThan($firstLike->id, $secondLike->id);
    }

    public function test_likes_tab_paginates_at_fifteen_posts(): void
    {
        $user = User::factory()->create();

        for ($index = 0; $index < 16; $index++) {
            $this->like($user, $this->createPost(User::factory()->create(), "Liked post {$index}"));
        }

        $posts = $this->getLikesPage($user);

        $this->assertInstanceOf(Paginator::class, $posts);
        $this->assertCount(15, $posts->items());
        $this->assertTrue($posts->hasMorePages());
    }

    public function test_likes_pagination_preserves_tab_query(): void
    {
        $user = User::factory()->create();

        for ($index = 0; $index < 16; $index++) {
            $this->like($user, $this->createPost(User::factory()->create(), "Paginated like {$index}"));
        }

        $posts = $this->getLikesPage($user);

        $this->assertStringContainsString('tab=likes', $posts->url(2));
    }

    public function test_tab_links_reset_page_when_switching_tabs(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=likes&page=5');
        $content = $response->getContent();
        $threadsUrl = route('user.profile', $user);
        $likesUrl = route('user.profile', ['username' => $user->username, 'tab' => 'likes']);

        $this->assertStringContainsString('href="'.$threadsUrl.'"', $content);
        $this->assertStringContainsString('href="'.$likesUrl.'"', $content);
        $this->assertStringNotContainsString($threadsUrl.'&page=5', $content);
        $this->assertStringNotContainsString($likesUrl.'&page=5', $content);
    }

    public function test_empty_likes_tab_uses_the_expected_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=likes');

        $response
            ->assertOk()
            ->assertSee('Belum ada utas yang disukai')
            ->assertDontSee('Pengguna ini belum membuat utas apa pun.');
    }

    public function test_guest_can_view_public_profile_tabs(): void
    {
        $user = User::factory()->create();

        $this->get(route('user.profile', $user).'?tab=likes')->assertOk();
    }

    public function test_threads_tab_keeps_profile_posts_and_ordering(): void
    {
        $user = User::factory()->create();
        $older = $this->createPost($user, 'Older thread');
        $newer = $this->createPost($user, 'Newer thread');
        $older->update(['created_at' => Carbon::parse('2026-08-31 13:00:00')]);
        $newer->update(['created_at' => Carbon::parse('2026-08-31 14:00:00')]);

        $posts = $this->getThreadsPage($user)->getCollection();

        $this->assertSame([$newer->id, $older->id], $posts->pluck('id')->all());
    }

    public function test_threads_tab_paginates_at_fifteen_posts(): void
    {
        $user = User::factory()->create();

        for ($index = 0; $index < 16; $index++) {
            $this->createPost($user, "Thread post {$index}");
        }

        $posts = $this->getThreadsPage($user);

        $this->assertCount(15, $posts->items());
        $this->assertTrue($posts->hasMorePages());
    }

    public function test_liked_repost_renders_the_original_post_and_author(): void
    {
        $user = User::factory()->create(['username' => 'liker']);
        $originalAuthor = User::factory()->create(['username' => 'original-author']);
        $original = $this->createPost($originalAuthor, 'Original liked content');
        $repost = $this->createPost($user, null, null, $original->id);
        $this->like($user, $repost);

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=likes');

        $response->assertOk()->assertSee('Original liked content')->assertSee('original-author');
        $response->assertSee('liker memposting ulang');
        $response->assertSee(route('posts.show', $original), false);
    }

    public function test_liked_multi_image_post_keeps_profile_media_slider(): void
    {
        $user = User::factory()->create();
        $post = $this->createPost(User::factory()->create(), 'Multi-image liked post', null, null, [
            'images' => ['media/one.jpg', 'media/two.jpg'],
        ]);
        $this->like($user, $post);

        $response = $this->actingAs($user)->get(route('user.profile', $user).'?tab=likes');

        $response
            ->assertOk()
            ->assertSee('data-media-slider="true"', false)
            ->assertSee('media/one.jpg', false)
            ->assertSee('media/two.jpg', false);
    }

    public function test_profile_threads_and_likes_render_viewer_bookmark_state(): void
    {
        $profile = User::factory()->create(['username' => 'bookmark-profile']);
        $viewer = User::factory()->create();
        $threadPost = $this->createPost($profile, 'Bookmarked profile thread');
        $likedPost = $this->createPost(User::factory()->create(), 'Bookmarked profile like');
        $this->like($profile, $likedPost);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $threadPost->id]);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $likedPost->id]);

        $threadsResponse = $this->actingAs($viewer)->get(route('user.profile', $profile));
        $likesResponse = $this->actingAs($viewer)->get(route('user.profile', $profile).'?tab=likes');

        $thread = $threadsResponse->viewData('posts')->getCollection()->first();
        $liked = $likesResponse->viewData('posts')->getCollection()->first();

        $this->assertTrue((bool) $thread->bookmarked_by_me);
        $this->assertTrue((bool) $liked->bookmarked_by_me);
        $this->assertFalse($thread->relationLoaded('bookmarks'));
        $this->assertFalse($liked->relationLoaded('bookmarks'));
        $threadsResponse->assertSee('aria-label="Hapus dari tersimpan"', false)
            ->assertSee(route('bookmarks.destroy', $threadPost), false);
        $likesResponse->assertSee('aria-label="Hapus dari tersimpan"', false)
            ->assertSee(route('bookmarks.destroy', $likedPost), false);
    }

    public function test_profile_bookmark_state_is_isolated_to_current_viewer(): void
    {
        $profile = User::factory()->create(['username' => 'isolated-profile']);
        $savedByOther = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($profile, 'Profile isolation post');
        Bookmark::create(['user_id' => $savedByOther->id, 'post_id' => $post->id]);

        $response = $this->actingAs($viewer)->get(route('user.profile', $profile));
        $listedPost = $response->viewData('posts')->getCollection()->first();

        $this->assertFalse((bool) $listedPost->bookmarked_by_me);
        $response->assertSee('aria-label="Simpan"', false)
            ->assertSee(route('bookmarks.store', $post), false);
    }

    public function test_profile_repost_bookmark_state_targets_the_original_post(): void
    {
        $profile = User::factory()->create(['username' => 'repost-profile']);
        $viewer = User::factory()->create();
        $original = $this->createPost(User::factory()->create(), 'Profile original bookmark target');
        $repost = $this->createPost($profile, null, null, $original->id);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $original->id]);

        $response = $this->actingAs($viewer)->get(route('user.profile', $profile));
        $listedPost = $response->viewData('posts')->getCollection()->first();

        $this->assertSame($repost->id, $listedPost->id);
        $this->assertTrue((bool) $listedPost->originalPost->bookmarked_by_me);
        $response->assertSee('aria-label="Hapus dari tersimpan"', false)
            ->assertSee(route('bookmarks.destroy', $original), false)
            ->assertDontSee(route('bookmarks.store', $repost), false);
    }

    public function test_profile_tabs_keep_bio_visible_and_follow_links(): void
    {
        $user = User::factory()->create(['bio' => 'Profile tab bio']);

        $response = $this->actingAs($user)->get(route('user.profile', $user));

        $response
            ->assertOk()
            ->assertSee('Profile tab bio')
            ->assertSee(route('user.following', $user), false)
            ->assertSee(route('user.followers', $user), false);
    }

    public function test_profile_tabs_keep_username_and_post_uid_route_keys(): void
    {
        $user = User::factory()->create(['username' => 'tab-owner']);
        $post = $this->createPost($user, 'Route-key thread');

        $response = $this->actingAs($user)->get(route('user.profile', $user));

        $response
            ->assertOk()
            ->assertSee(route('user.profile', 'tab-owner'), false)
            ->assertSee(route('posts.show', $post), false);
        $this->assertSame('tab-owner', $user->getRouteKey());
        $this->assertSame($post->uid, $post->getRouteKey());
    }

    public function test_liked_repost_listing_keeps_raw_liked_post_semantics(): void
    {
        $user = User::factory()->create();
        $original = $this->createPost(User::factory()->create(), 'Original raw post');
        $repost = $this->createPost($user, null, null, $original->id);
        $this->like($user, $original);
        $this->like($user, $repost);

        $posts = $this->getLikesPage($user)->getCollection();

        $this->assertCount(2, $posts);
        $this->assertSame([$repost->id, $original->id], $posts->pluck('id')->all());
    }

    private function createPost(
        User $user,
        ?string $content,
        ?string $topic = null,
        ?int $repostId = null,
        array $attributes = [],
    ): Post {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => $content,
            'topic' => $topic,
            'repost_id' => $repostId,
        ], $attributes));
    }

    private function like(User $user, Post $post, ?string $createdAt = null): Like
    {
        return Like::create(array_filter([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => $createdAt,
        ], static fn ($value): bool => $value !== null));
    }

    private function getLikesPage(User $user, string $query = '?tab=likes')
    {
        return $this->actingAs($user)
            ->get(route('user.profile', $user).$query)
            ->viewData('posts');
    }

    private function getThreadsPage(User $user)
    {
        return $this->actingAs($user)
            ->get(route('user.profile', $user))
            ->viewData('posts');
    }
}
