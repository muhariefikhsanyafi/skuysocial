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

        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $postCard = $xpath->query('//*[@data-feed-post-uid="'.$post->uid.'"]')->item(0);

        $this->assertNotNull($postCard, 'Search posts should reuse the shared Feed card.');
        $this->assertGreaterThanOrEqual(2, $xpath->query('//*[@aria-label="'.e(__('threads.verified')).'"]')->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="like"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="repost"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="bookmark"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-comment-action]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-post-appreciation]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-mobile-primary-action="share"]', $postCard)->length);
    }

    public function test_search_post_actions_render_the_viewers_persisted_bookmark_state(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['username' => 'saved-search-author']);
        $post = Post::create([
            'user_id' => $author->id,
            'content' => 'Saved search action parity query',
        ]);
        Bookmark::create(['user_id' => $viewer->id, 'post_id' => $post->id]);

        $response = $this->actingAs($viewer)->get(route('search', ['q' => 'Saved search action parity']));
        $html = $response->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $postCard = $xpath->query('//*[@data-feed-post-uid="'.$post->uid.'"]')->item(0);
        $bookmark = $xpath->query('.//*[@data-feed-post-action="bookmark"]', $postCard)->item(0);

        $response->assertOk();
        $this->assertNotNull($postCard);
        $this->assertNotNull($bookmark);
        $this->assertSame('true', $bookmark->getAttribute('aria-pressed'));
        $this->assertSame(route('bookmarks.destroy', $post->uid), $bookmark->parentNode->getAttribute('action'));
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

    public function test_saved_posts_use_feed_identity_media_actions_and_owner_scoped_unsave_state(): void
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

        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $savedPage = $xpath->query('//*[@data-skuy-prism-saved]')->item(0);
        $this->assertNotNull($savedPage, 'Saved should have a stable Prism page root.');
        $this->assertSame(1, $xpath->query('.//*[@data-saved-page-header]', $savedPage)->length);
        $this->assertSame(2, $xpath->query('.//article[@data-feed-post-uid]', $savedPage)->length);

        $postCard = $xpath->query('.//article[@data-feed-post-uid="'.$post->uid.'"]', $savedPage)->item(0);
        $this->assertNotNull($postCard);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="like"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="repost"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="bookmark"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-comment-action]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-post-appreciation]', $postCard)->length);
        $this->assertSame(3, $xpath->query('.//*[@data-media-slide="true"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-skuy-media-viewer]', $savedPage)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-saved-post-collection]', $savedPage)->length);

        $singleImageCard = $xpath->query('.//article[@data-feed-post-uid="'.$singleImagePost->uid.'"]', $savedPage)->item(0);
        $this->assertNotNull($singleImageCard);
        $this->assertSame(0, $xpath->query('.//*[@data-media-slide="true"]', $singleImageCard)->length);
        $this->assertTrue(str_contains($html, asset('storage/posts/saved-single.jpg')), 'Legacy single-image posts must remain visible in Saved.');
        $this->assertTrue(str_contains($html, 'Saved Display Name'));
        $this->assertTrue(str_contains($html, __('threads.verified')));
        $this->assertTrue(str_contains($html, '@</span>saved.author'));
        $this->assertTrue(str_contains($html, route('posts.show', $post->uid)));
        $this->assertTrue(str_contains($html, route('bookmarks.destroy', $post->uid)));
        $this->assertTrue(str_contains($html, 'aria-label="'.e(__('threads.actions.remove_from_saved')).'"'));
    }

    public function test_saved_empty_state_has_a_premium_status_and_feed_destination(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('bookmarks.index'));
        $response->assertOk();
        $html = $response->getContent();

        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $savedPage = $xpath->query('//*[@data-skuy-prism-saved]')->item(0);
        $this->assertNotNull($savedPage);
        $emptyState = $xpath->query('.//*[@data-saved-empty-state]', $savedPage)->item(0);
        $this->assertNotNull($emptyState);
        $this->assertSame('status', $emptyState->getAttribute('role'));
        $this->assertSame(0, $xpath->query('.//article[@data-feed-post-uid]', $savedPage)->length);
        $this->assertSame(1, $xpath->query('.//a[@href="'.route('feed').'"]', $emptyState)->length);
        $this->assertTrue(str_contains($html, __('saved.empty')));
        $this->assertTrue(str_contains($html, __('saved.empty_description')));
    }

    public function test_saved_empty_later_page_keeps_navigation_to_the_collection(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();

        foreach (range(1, 15) as $index) {
            $post = Post::create([
                'user_id' => $author->id,
                'content' => 'Saved page fixture '.$index,
            ]);
            Bookmark::create(['user_id' => $viewer->id, 'post_id' => $post->id]);
        }

        $response = $this->actingAs($viewer)->get(route('bookmarks.index', ['page' => 2]));
        $response->assertOk();
        $posts = $response->viewData('posts');
        $this->assertSame(2, $posts->currentPage());
        $this->assertTrue($posts->isEmpty());
        $this->assertGreaterThan(1, $posts->currentPage());

        $html = $response->getContent();
        $this->assertTrue(str_contains($html, __('saved.page_empty_title')));
        $this->assertTrue(str_contains($html, __('saved.page_empty_description')));
        $this->assertTrue(str_contains($html, 'data-saved-collection-link'));
        $this->assertTrue(str_contains($html, 'href="'.route('bookmarks.index').'"'), 'An empty later page must still link back to the first Saved page.');
        $this->assertFalse(str_contains($html, 'data-saved-feed-link'), 'A later empty page should lead back through Saved rather than the global Feed empty state.');
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
