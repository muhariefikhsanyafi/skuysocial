<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Bookmark;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InertiaPostDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_detail_pilot_is_enabled_by_default(): void
    {
        $this->assertTrue(config('frontend.post_detail_inertia'));
    }

    public function test_inertia_post_detail_returns_an_explicit_normal_post_contract(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $owner = User::factory()->create([
            'username' => 'pilot-owner',
            'is_verified' => true,
            'bio' => 'An architect writing about inclusive spaces.',
        ]);
        $post = $this->createPost($owner, [
            'content' => 'Pilot detail content',
            'topic' => 'pilot-topic',
            'images' => ['posts/pilot-a.jpg', 'posts/pilot-b.jpg'],
        ]);
        $root = $this->createComment($post, $owner, 'Pilot root comment');
        $reply = $this->createComment($post, $owner, 'Pilot reply', ['parent_id' => $root->id]);
        Like::query()->create(['post_id' => $post->id, 'user_id' => $owner->id]);
        Bookmark::query()->create(['post_id' => $post->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('posts.show', $post));

        $response
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Post/Show')
            ->assertJsonPath('props.post.uid', $post->uid)
            ->assertJsonPath('props.post.is_repost', false)
            ->assertJsonPath('props.display_post.uid', $post->uid)
            ->assertJsonPath('props.display_post.content', 'Pilot detail content')
            ->assertJsonPath('props.display_post.author.id', $owner->id)
            ->assertJsonPath('props.display_post.author.username', 'pilot-owner')
            ->assertJsonPath('props.display_post.author.is_verified', true)
            ->assertJsonPath('props.display_post.topic', 'pilot-topic')
            ->assertJsonPath('props.display_post.interactions.liked', true)
            ->assertJsonPath('props.display_post.interactions.like_count', 1)
            ->assertJsonPath('props.display_post.interactions.bookmarked', true)
            ->assertJsonPath('props.display_post.interactions.comment_count', 2)
            ->assertJsonPath('props.display_post.capabilities.can_edit', true)
            ->assertJsonPath('props.display_post.capabilities.can_delete', true)
            ->assertJsonPath('props.author_context.bio', 'An architect writing about inclusive spaces.')
            ->assertJsonPath('props.author_context.posts_count', 1)
            ->assertJsonPath('props.author_context.followers_count', 0)
            ->assertJsonPath('props.author_context.followings_count', 0)
            ->assertJsonPath('props.viewer.can_perform_social_writes', true)
            ->assertJsonPath('props.comments.data.0.id', $root->id)
            ->assertJsonPath('props.comments.data.0.replies.0.id', $reply->id)
            ->assertJsonPath('props.expanded_reply_root_id', null)
            ->assertJsonPath('props.expanded_replies', null)
            ->assertJsonPath('props.routes.author_profile', route('user.profile', $owner))
            ->assertJsonPath('props.routes.comment', route('comments.store', $post))
            ->assertJsonPath('props.routes.like', route('posts.like', $post))
            ->assertJsonPath('props.routes.bookmark_store', route('bookmarks.store', $post))
            ->assertJsonPath('props.routes.edit', route('posts.edit', $post))
            ->assertJsonPath('props.routes.delete', route('posts.destroy', $post))
            ->assertJsonPath('props.translations.thread', __('threads.labels.thread'))
            ->assertJsonPath('props.translations.about_creator', __('profile.about_creator'));

        $displayPost = $response->json('props.display_post');
        $this->assertSame(
            [asset('storage/posts/pilot-a.jpg'), asset('storage/posts/pilot-b.jpg')],
            array_column($displayPost['media'], 'url'),
        );
        $this->assertArrayNotHasKey('email', $displayPost['author']);
        $this->assertArrayNotHasKey('password', $displayPost['author']);
        $this->assertArrayNotHasKey('email', $response->json('props.viewer.author'));
        $this->assertArrayNotHasKey('password', $response->json('props.viewer.author'));
        $this->assertSame(
            ['bio', 'posts_count', 'followers_count', 'followings_count'],
            array_keys($response->json('props.author_context')),
        );
    }

    public function test_inertia_post_detail_preserves_repost_context_and_non_owner_capabilities(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $originalAuthor = User::factory()->create(['username' => 'pilot-original']);
        $reposter = User::factory()->create(['username' => 'pilot-reposter']);
        $viewer = User::factory()->create(['username' => 'pilot-viewer']);
        $original = $this->createPost($originalAuthor, ['content' => 'Original pilot content']);
        $repost = $this->createPost($reposter, [
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $response = $this->actingAs($viewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('posts.show', $repost));

        $response
            ->assertOk()
            ->assertJsonPath('component', 'Post/Show')
            ->assertJsonPath('props.post.uid', $repost->uid)
            ->assertJsonPath('props.post.is_repost', true)
            ->assertJsonPath('props.post.reposted_by.username', 'pilot-reposter')
            ->assertJsonPath('props.display_post.uid', $original->uid)
            ->assertJsonPath('props.display_post.author.username', 'pilot-original')
            ->assertJsonPath('props.display_post.capabilities.can_edit', false)
            ->assertJsonPath('props.display_post.capabilities.can_delete', false)
            ->assertJsonPath('props.routes.edit', null)
            ->assertJsonPath('props.routes.delete', null)
            ->assertJsonPath('props.routes.like', route('posts.like', $original))
            ->assertJsonPath('props.routes.share', route('posts.show', $repost));

        $ownerResponse = $this->actingAs($reposter)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('posts.show', $repost));

        $ownerResponse
            ->assertOk()
            ->assertJsonPath('props.display_post.capabilities.can_edit', false)
            ->assertJsonPath('props.display_post.capabilities.can_delete', true)
            ->assertJsonPath('props.routes.edit', null)
            ->assertJsonPath('props.routes.delete', route('posts.destroy', $repost));
    }

    public function test_inertia_post_detail_preserves_root_pagination_and_reply_cursor_contract(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $author = User::factory()->create();
        $viewer = User::factory()->create(['locale' => 'en']);
        $post = $this->createPost($author);
        $roots = collect();

        for ($number = 1; $number <= 21; $number++) {
            $root = $this->createComment($post, $author, "Pilot root {$number}");
            $roots->push($root);

            for ($replyNumber = 1; $replyNumber <= 12; $replyNumber++) {
                $this->createComment($post, $viewer, "Pilot reply {$number}-{$replyNumber}", [
                    'parent_id' => $root->id,
                ]);
            }
        }

        $initialResponse = $this->actingAs($viewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('posts.show', $post));
        $initialResponse->assertOk();
        $initialComments = $initialResponse->json('props.comments');

        $this->assertCount(20, $initialComments['data']);
        $this->assertTrue($initialComments['has_more']);
        $this->assertStringContainsString('comments_page=2', $initialComments['next_url']);
        $this->assertNull($initialResponse->json('props.expanded_replies'));

        $root = $roots->last();
        $expansionUrl = route('posts.show', [
            'post' => $post,
            'comments_page' => 1,
            'replies_for' => $root->id,
        ]);
        $expandedResponse = $this->actingAs($viewer)
            ->withHeaders($this->inertiaHeaders())
            ->get($expansionUrl);

        $expandedResponse->assertOk();
        $expandedReplies = $expandedResponse->json('props.expanded_replies');

        $this->assertSame($root->id, $expandedResponse->json('props.expanded_reply_root_id'));
        $this->assertCount(10, $expandedReplies['data']);
        $this->assertTrue($expandedReplies['has_more']);
        $this->assertStringContainsString('replies_cursor=', $expandedReplies['next_url']);
        $this->assertStringContainsString('comments_page=1', $expandedReplies['next_url']);
        $this->assertStringContainsString('replies_for='.$root->id, $expandedReplies['next_url']);
        $this->assertStringNotContainsString(
            'replies_for=',
            $expandedResponse->json('props.comments.next_url'),
        );
    }

    public function test_restricted_viewer_receives_read_props_but_social_write_capabilities_are_disabled(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $viewer = User::factory()->create();
        $viewer->forceFill(['moderation_state' => 'restricted'])->save();
        $post = $this->createPost($viewer);

        $response = $this->actingAs($viewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('posts.show', $post));

        $response
            ->assertOk()
            ->assertJsonPath('component', 'Post/Show')
            ->assertJsonPath('props.moderation.state', 'restricted')
            ->assertJsonPath('props.moderation.allows_social_writes', false)
            ->assertJsonPath('props.viewer.can_perform_social_writes', false)
            ->assertJsonPath('props.display_post.capabilities.can_perform_social_writes', false)
            ->assertJsonPath('props.display_post.capabilities.can_edit', false)
            ->assertJsonPath('props.display_post.capabilities.can_delete', false)
            ->assertJsonPath('props.routes.edit', null)
            ->assertJsonPath('props.routes.delete', null);
    }

    public function test_inertia_post_detail_delete_uses_a_full_navigation_to_the_blade_feed(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $owner = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->delete(route('posts.destroy', $post));

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('feed'));
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_inertia_unrepost_from_wrapper_detail_navigates_to_original_thread(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $owner = User::factory()->create();
        $original = $this->createPost(User::factory()->create());
        $wrapper = $this->createPost($owner, [
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $response = $this->actingAs($owner)
            ->withHeaders($this->inertiaHeaders())
            ->post(route('posts.repost', $original));

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('posts.show', $original));
        $this->assertDatabaseMissing('posts', ['id' => $wrapper->id]);
    }

    public function test_guest_post_detail_access_remains_authenticated_only(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $post = $this->createPost(User::factory()->create());

        $this->get(route('posts.show', $post))
            ->assertRedirect(route('login'));
    }

    public function test_initial_post_detail_visit_bootstraps_the_inertia_root_shell(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $viewer = User::factory()->create();
        $post = $this->createPost(User::factory()->create(), ['content' => 'Initial Inertia visit']);

        $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('data-page="app"', false)
            ->assertSee('Post\\/Show', false)
            ->assertSee('Initial Inertia visit', false);
    }

    public function test_inertia_request_redirects_to_blade_when_pilot_flag_is_disabled(): void
    {
        config(['frontend.post_detail_inertia' => false]);

        $viewer = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $this->actingAs($viewer)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('posts.show', $post))
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('posts.show', $post));
    }

    public function test_post_detail_flag_can_fall_back_to_blade_without_converting_feed(): void
    {
        config(['frontend.post_detail_inertia' => false]);

        $viewer = User::factory()->create();
        $post = $this->createPost(User::factory()->create());

        $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertViewIs('show-post')
            ->assertViewHas('post', fn (Post $viewPost): bool => $viewPost->id === $post->id);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertViewIs('feed');

        config(['frontend.post_detail_inertia' => true]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertViewIs('feed');
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::query()->create(array_merge([
            'user_id' => $user->id,
            'content' => 'Pilot detail post',
        ], $attributes));
    }

    private function createComment(Post $post, User $user, string $content, array $attributes = []): Comment
    {
        return Comment::query()->create(array_merge([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => null,
        ], $attributes));
    }
}
