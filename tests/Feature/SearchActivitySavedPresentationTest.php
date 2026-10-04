<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Models\Bookmark;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostLiked;
use App\Notifications\UserFollowed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchActivitySavedPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_activity_and_saved_omit_the_empty_shell_notice(): void
    {
        $viewer = User::factory()->create();
        $urls = [
            route('search'),
            route('activity.index'),
            route('bookmarks.index'),
        ];

        foreach ($urls as $url) {
            $response = $this->actingAs($viewer)->get($url);
            $response->assertOk();

            $html = $response->getContent();
            $this->assertStringNotContainsString('class="skuy-shell-notice', $html);
            $this->assertTrue(str_contains($html, 'aria-current="page"'), 'The active authenticated navigation item should remain selected.');
        }
    }

    public function test_search_results_preserve_destinations_and_present_verified_identity(): void
    {
        $viewer = User::factory()->create();
        $person = User::factory()->create([
            'name' => 'Discovery Person',
            'username' => 'discovery.person',
        ]);
        $person->forceFill(['is_verified' => true])->save();
        $author = User::factory()->create([
            'name' => 'Discovery Author',
            'username' => 'discovery.author',
        ]);
        $author->forceFill(['is_verified' => true])->save();
        $post = Post::create([
            'user_id' => $author->id,
            'content' => 'A discovery thread for this search.',
            'topic' => 'discovery-topic',
        ]);
        $hashtag = Hashtag::create(['name' => 'discovery']);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'discovery']));
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('value="discovery"', false)
            ->assertSee('Discovery Person')
            ->assertSee('@discovery.person')
            ->assertSee('Discovery Author')
            ->assertSee(route('user.profile', $person->username), false)
            ->assertSee(route('posts.show', $post), false)
            ->assertSee(route('tags.show', $hashtag->name), false)
            ->assertSee(route('topik.show', ['topic' => 'discovery-topic']), false);

        $verificationCount = substr_count($html, 'class="skuy-discovery-verified"');
        $this->assertGreaterThanOrEqual(
            2,
            $verificationCount,
            'Verified users and post authors should show the existing verification marker.',
        );
        $this->assertSame($verificationCount, preg_match_all('/class="skuy-discovery-verified" role="img"/', $html), 'Assistive technology should identify each verification mark as an image with its existing label.');
    }

    public function test_search_validation_error_is_announced_and_the_string_query_is_preserved(): void
    {
        $viewer = User::factory()->create();
        $invalidQuery = str_repeat('x', 101);

        $response = $this->followingRedirects()
            ->from(route('search'))
            ->actingAs($viewer)
            ->get(route('search', ['q' => $invalidQuery]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertTrue(str_contains($html, 'id="discovery-search-error"'), 'The validation alert should be rendered.');
        $this->assertTrue(str_contains($html, 'aria-invalid="true"'), 'The input should expose its invalid state.');
        $this->assertTrue(str_contains($html, 'aria-describedby="discovery-search-error"'), 'The input should reference its validation alert.');
        $this->assertTrue(str_contains($html, 'value="'.$invalidQuery.'"'), 'The prior string query should remain in the input.');
        $this->assertFalse(str_contains($html, 'id="search-empty-title"'), 'Do not pair an invalid query alert with the initial empty state.');
    }

    public function test_search_initial_and_no_result_states_remain_compact_and_announced(): void
    {
        $viewer = User::factory()->create();

        $initial = $this->actingAs($viewer)->get(route('search'));
        $initial->assertOk();
        $this->assertTrue(str_contains($initial->getContent(), 'id="search-empty-title"'));

        $empty = $this->actingAs($viewer)->get(route('search', ['q' => 'no-match-for-this-fixture']));
        $empty->assertOk();
        $html = $empty->getContent();

        $this->assertTrue(str_contains($html, __('search.empty.users')));
        $this->assertTrue(str_contains($html, __('search.empty.threads')));
        $this->assertTrue(str_contains($html, 'role="status"'));
    }

    public function test_activity_renders_existing_unread_and_read_states_without_changing_read_behavior(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create(['username' => 'activity-like-actor']);
        $follower = User::factory()->create(['username' => 'activity-follow-actor']);
        $post = Post::create(['user_id' => $recipient->id, 'content' => 'Activity target post']);

        $recipient->notify(new PostLiked($actor, $post));
        $recipient->notify(new UserFollowed($follower));
        $readNotification = $recipient->notifications()->latest('id')->first();
        $readNotification->markAsRead();
        $this->assertSame(1, $recipient->unreadNotifications()->count(), 'One unread activity item should exist before the existing visit-to-read action.');

        $response = $this->actingAs($recipient)->get(route('activity.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'data-notification-read="read"'), 'Opening Activity must render the persisted read state after marking unread notifications read.');
        $this->assertSame(0, substr_count($html, 'data-notification-read="unread"'));
        $this->assertFalse(str_contains($html, 'skuy-activity-item--unread'));
        $this->assertSame(0, $recipient->unreadNotifications()->count());
        $this->assertNotNull($readNotification->fresh()->read_at);

        $recipient->forceFill(['locale' => 'en'])->save();
        $this->app->setLocale('en');
        $readOnly = $this->get(route('activity.index'));
        $readOnly->assertOk();
        $this->assertTrue(str_contains($readOnly->getContent(), '<span class="sr-only">Read</span>'));
    }

    public function test_activity_empty_state_uses_the_compact_status_component(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('activity.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertTrue(str_contains($html, 'class="skuy-social-empty"'));
        $this->assertTrue(str_contains($html, 'role="status"'));
    }

    public function test_saved_posts_use_feed_identity_media_and_keep_the_existing_unsave_destination(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create([
            'name' => 'Saved Display Name',
            'username' => 'saved.author',
        ]);
        $author->forceFill(['is_verified' => true])->save();
        $post = Post::create([
            'user_id' => $author->id,
            'content' => 'Saved media post content.',
            'images' => ['posts/saved-a.jpg', 'posts/saved-b.jpg', 'posts/saved-c.jpg'],
        ]);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $post->id]);
        $singleImagePost = Post::create([
            'user_id' => $author->id,
            'content' => 'Saved single-image post.',
            'image' => 'posts/saved-single.jpg',
        ]);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $singleImagePost->id]);

        $response = $this->actingAs($viewer)->get(route('bookmarks.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertTrue(str_contains($html, 'Saved Display Name'), 'The saved post should lead with its author display name.');
        $this->assertTrue(str_contains($html, __('threads.verified')), 'Saved verified authors should retain the available verification badge.');
        $this->assertTrue(str_contains($html, '@</span>saved.author'), 'The username should remain secondary identity metadata.');
        $this->assertTrue(str_contains($html, 'skuy-feed-post__media--carousel'), 'Saved media should reuse Feed carousel geometry.');
        $this->assertTrue(str_contains($html, 'skuy-saved-post__single-image'), 'Single saved media should remain visible and preserve its intrinsic ratio.');
        $this->assertTrue(str_contains($html, route('posts.show', $post->uid)), 'The post destination must remain unchanged.');
        $this->assertTrue(str_contains($html, route('bookmarks.destroy', $post->uid)), 'The existing unsave destination must remain unchanged.');
        $this->assertTrue(str_contains($html, 'aria-label="'.e(__('threads.actions.remove_from_saved')).'"'));
        $this->assertSame(3, substr_count($html, 'skuy-feed-media-item--portrait-preview'));
    }

    public function test_saved_empty_state_is_compact_and_announced(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('bookmarks.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertTrue(str_contains($html, 'class="skuy-social-empty"'));
        $this->assertTrue(str_contains($html, 'role="status"'));
        $this->assertTrue(str_contains($html, __('saved.empty')));
    }

    public function test_restricted_activity_keeps_the_notice_inside_the_shell(): void
    {
        $viewer = User::factory()->create();
        $viewer->forceFill(['moderation_state' => ModerationState::Restricted->value])->save();

        $response = $this->actingAs($viewer)->get(route('activity.index'));
        $response->assertOk();
        $html = $response->getContent();

        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $notice = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " skuy-app-shell ")]'
            .'//div[contains(concat(" ", normalize-space(@class), " "), " skuy-shell-notice ")'
            .' and contains(concat(" ", normalize-space(@class), " "), " skuy-shell-notice--content ")]',
        );
        $this->assertSame(1, $notice->length, 'The restricted notice should retain both class tokens inside the app shell.');
        $this->assertTrue(str_contains($html, 'id="restricted-account-notice-title"'));
        $this->assertTrue(str_contains($html, __('moderation.restricted.notice_title')));
        $this->assertTrue(str_contains($html, __('moderation.restricted.notice_body')));
    }
}
