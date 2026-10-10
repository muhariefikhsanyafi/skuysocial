<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Preserve the existing post-detail Blade assertions while the pilot has its own contract tests.
        config(['frontend.post_detail_inertia' => false]);
    }

    public function test_guest_cannot_access_bookmark_endpoints(): void
    {
        $owner = User::factory()->create();
        $post = $this->createPost($owner);

        $this->get(route('bookmarks.index'))->assertRedirect(route('login'));
        $this->post(route('bookmarks.store', $post))->assertRedirect(route('login'));
        $this->delete(route('bookmarks.destroy', $post))->assertRedirect(route('login'));
    }

    public function test_user_can_bookmark_a_post(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $response = $this->actingAs($viewer)->post(route('bookmarks.store', $post));

        $response->assertRedirect();
        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_duplicate_bookmark_post_is_idempotent_and_remains_unique(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $this->actingAs($viewer)->post(route('bookmarks.store', $post))->assertRedirect();
        $this->actingAs($viewer)->post(route('bookmarks.store', $post))->assertRedirect();

        $this->assertSame(1, Bookmark::query()
            ->where('user_id', $viewer->id)
            ->where('post_id', $post->id)
            ->count());
    }

    public function test_user_can_remove_a_bookmark(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);
        $this->createBookmark($viewer, $post);

        $response = $this->actingAs($viewer)->delete(route('bookmarks.destroy', $post));

        $response->assertRedirect();
        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_repeated_bookmark_delete_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);
        $this->createBookmark($viewer, $post);

        $this->actingAs($viewer)->delete(route('bookmarks.destroy', $post))->assertRedirect();
        $this->actingAs($viewer)->delete(route('bookmarks.destroy', $post))->assertRedirect();

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_cannot_remove_another_users_bookmark(): void
    {
        $owner = User::factory()->create();
        $bookmarkOwner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = $this->createPost($owner);
        $this->createBookmark($bookmarkOwner, $post);

        $this->actingAs($otherUser)
            ->delete(route('bookmarks.destroy', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $bookmarkOwner->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_saved_page_contains_only_current_users_bookmarks(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();
        $viewerPost = $this->createPost($owner, ['content' => 'Viewer saved post']);
        $otherPost = $this->createPost($owner, ['content' => 'Other saved post']);
        $this->createBookmark($viewer, $viewerPost);
        $this->createBookmark($otherUser, $otherPost);

        $response = $this->actingAs($viewer)->get(route('bookmarks.index'));
        $posts = $response->viewData('posts');

        $response->assertOk();
        $this->assertSame([$viewerPost->id], $posts->getCollection()->pluck('id')->all());
        $response->assertSee('Viewer saved post')
            ->assertDontSee('Other saved post');
    }

    public function test_saved_page_is_newest_bookmark_first_and_bounded_to_fifteen(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $baseTime = Carbon::create(2026, 9, 4, 12, 0, 0);
        $posts = collect();

        for ($number = 1; $number <= 16; $number++) {
            $post = $this->createPost($owner, ['content' => "Saved post {$number}"]);
            $posts->push($post);
            $this->createBookmark($viewer, $post, [
                'created_at' => $baseTime,
                'updated_at' => $baseTime,
            ]);
        }

        $pageOne = $this->actingAs($viewer)->get(route('bookmarks.index'));
        $paginator = $pageOne->viewData('posts');

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertCount(15, $paginator->getCollection());
        $this->assertTrue($paginator->hasMorePages());
        $this->assertSame(
            $posts->reverse()->take(15)->pluck('id')->all(),
            $paginator->getCollection()->pluck('id')->all(),
        );

        $pageTwo = $this->actingAs($viewer)->get(route('bookmarks.index', ['page' => 2]));
        $pageTwoPosts = $pageTwo->viewData('posts');

        $this->assertCount(1, $pageTwoPosts->getCollection());
        $this->assertSame($posts->first()->id, $pageTwoPosts->getCollection()->first()->id);
    }

    public function test_bookmark_routes_use_the_post_uid(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $this->assertSame('uid', $post->getRouteKeyName());
        $this->assertStringContainsString(
            '/posts/'.$post->uid.'/bookmark',
            route('bookmarks.store', $post),
        );

        $this->actingAs($viewer)->post(route('bookmarks.store', $post))->assertRedirect();
        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_unrelated_database_failure_is_not_treated_as_duplicate_success(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);
        DB::statement('DROP TABLE bookmarks');

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->actingAs($viewer)->post(route('bookmarks.store', $post));
    }

    public function test_deleting_a_bookmarked_post_cascades_to_bookmarks(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);
        $this->createBookmark($viewer, $post);

        $post->delete();

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_deleting_a_bookmarking_user_cascades_to_bookmarks(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);
        $this->createBookmark($viewer, $post);

        $viewer->delete();

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $viewer->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_feed_renders_saved_and_unsaved_bookmark_state(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $unsavedResponse = $this->actingAs($viewer)->get(route('feed'));
        $unsavedPost = $unsavedResponse->viewData('posts')->getCollection()->firstWhere('id', $post->id);

        $this->assertFalse((bool) $unsavedPost->bookmarked_by_me);
        $unsavedResponse->assertSee('aria-label="Simpan"', false);

        $this->createBookmark($viewer, $post);
        $savedResponse = $this->actingAs($viewer)->get(route('feed'));
        $savedPost = $savedResponse->viewData('posts')->getCollection()->firstWhere('id', $post->id);

        $this->assertTrue((bool) $savedPost->bookmarked_by_me);
        $savedResponse->assertSee('aria-label="Hapus dari tersimpan"', false);
    }

    public function test_feed_more_menus_toggle_bookmarks_inline_outside_the_mobile_primary_row(): void
    {
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownedPost = $this->createPost($viewer, ['content' => 'Owned mobile menu post']);
        $otherPost = $this->createPost($otherUser, ['content' => 'Other mobile menu post']);
        $this->createBookmark($viewer, $otherPost);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $html = (string) $response->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $response->assertOk();
        foreach ([$ownedPost, $otherPost] as $post) {
            $options = $xpath->query('//*[@data-mobile-post-options="'.$post->uid.'"]');
            $this->assertSame(1, $options->length, 'Each Feed post should expose its own More menu.');

            $bookmarkForms = $xpath->query(
                './/form[contains(concat(" ", normalize-space(@class), " "), " skuy-bookmark-menu-action ")]',
                $options->item(0),
            );
            $this->assertSame(1, $bookmarkForms->length, 'Save/Unsave should stay inside the post More menu.');

            /** @var \DOMElement $form */
            $form = $bookmarkForms->item(0);
            $this->assertSame('toggleBookmark($event)', $form->getAttribute('@submit.prevent'));
            $this->assertStringContainsString('likeControl({', $form->getAttribute('x-data'));
            $this->assertSame('syncPeerState($event)', $form->getAttribute('@skuy-post-action-state.window'));
            $this->assertSame(
                $post->is($otherPost),
                str_contains($form->getAttribute('class'), 'skuy-post-options__bookmark'),
                'Only the non-owner mobile-only menu should use the mobile visibility class.',
            );
            $saved = $post->is($otherPost);
            $this->assertSame(
                route($saved ? 'bookmarks.destroy' : 'bookmarks.store', $post->uid),
                $form->getAttribute('action'),
            );

            $menuItems = $xpath->query('.//button[@role="menuitem"]', $form);
            $this->assertSame(1, $menuItems->length);
            /** @var \DOMElement $menuItem */
            $menuItem = $menuItems->item(0);
            $this->assertSame(
                $saved ? __('threads.actions.remove_from_saved') : __('threads.actions.save'),
                $menuItem->getAttribute('aria-label'),
            );
            $this->assertSame($saved ? 'true' : 'false', $menuItem->getAttribute('aria-pressed'));

            $primaryBookmark = $xpath->query(
                './/*[@data-mobile-primary-action="bookmark"]',
                $options->item(0)->parentNode->parentNode,
            );
            $this->assertSame(0, $primaryBookmark->length, 'Bookmark must not be a mobile primary action.');
        }

        $this->assertSame(2, substr_count($html, 'syncBookmarkState($event)'));
    }

    public function test_post_detail_renders_saved_and_unsaved_bookmark_state(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($owner);

        $unsavedResponse = $this->actingAs($viewer)->get(route('posts.show', $post));
        $unsavedPost = $unsavedResponse->viewData('displayPost');

        $this->assertFalse((bool) $unsavedPost->bookmarked_by_current_user);
        $unsavedResponse->assertSee('aria-label="Simpan"', false);

        $this->createBookmark($viewer, $post);
        $savedResponse = $this->actingAs($viewer)->get(route('posts.show', $post));
        $savedPost = $savedResponse->viewData('displayPost');

        $this->assertTrue((bool) $savedPost->bookmarked_by_current_user);
        $savedResponse->assertSee('aria-label="Hapus dari tersimpan"', false);
    }

    public function test_saved_state_is_consistent_across_all_post_listing_surfaces(): void
    {
        $owner = User::factory()->create(['username' => 'parity-owner']);
        $viewer = User::factory()->create(['username' => 'parity-viewer']);
        $hashtag = Hashtag::create(['name' => 'bookmark-parity']);
        $post = $this->createPost($owner, [
            'content' => 'Cross surface bookmark parity',
            'topic' => 'bookmark-parity-topic',
        ]);
        $post->hashtags()->attach($hashtag->id);
        $this->createBookmark($viewer, $post);

        $feedPost = $this->actingAs($viewer)
            ->get(route('feed'))
            ->viewData('posts')
            ->getCollection()
            ->firstWhere('id', $post->id);
        $detailPost = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('displayPost');
        $profilePost = $this->actingAs($viewer)
            ->get(route('user.profile', $owner))
            ->viewData('posts')
            ->getCollection()
            ->firstWhere('id', $post->id);
        $hashtagPost = $this->actingAs($viewer)
            ->get(route('tags.show', $hashtag->name))
            ->viewData('posts')
            ->getCollection()
            ->firstWhere('id', $post->id);
        $topicPost = $this->actingAs($viewer)
            ->get(route('topik.show', 'bookmark-parity-topic'))
            ->viewData('posts')
            ->getCollection()
            ->firstWhere('id', $post->id);
        $searchPost = $this->actingAs($viewer)
            ->get(route('search', ['q' => 'Cross surface bookmark']))
            ->viewData('posts')
            ->getCollection()
            ->firstWhere('id', $post->id);

        $this->assertTrue((bool) $feedPost->bookmarked_by_me);
        $this->assertTrue((bool) $detailPost->bookmarked_by_current_user);
        $this->assertTrue((bool) $profilePost->bookmarked_by_me);
        $this->assertTrue((bool) $hashtagPost->bookmarked_by_me);
        $this->assertTrue((bool) $topicPost->bookmarked_by_me);
        $this->assertTrue((bool) $searchPost->bookmarked_by_me);
    }

    public function test_bookmarking_a_repost_saves_the_effective_original_post(): void
    {
        $owner = User::factory()->create();
        $reposter = User::factory()->create();
        $original = $this->createPost($owner, ['content' => 'Original saved content']);
        $repost = $this->createPost($reposter, [
            'content' => null,
            'repost_id' => $original->id,
        ]);

        $this->actingAs($reposter)
            ->post(route('bookmarks.store', $repost))
            ->assertRedirect();

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $reposter->id,
            'post_id' => $original->id,
        ]);
        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $reposter->id,
            'post_id' => $repost->id,
        ]);

        $savedResponse = $this->actingAs($reposter)->get(route('bookmarks.index'));

        $savedResponse->assertOk()
            ->assertSee('Original saved content')
            ->assertSee($owner->username);
    }

    public function test_saved_page_navigation_uses_sidebar_and_mobile_drawer(): void
    {
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('feed'));
        $content = $response->getContent();

        $response->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($content);
        $xpath = new \DOMXPath($document);
        $bookmarkUrl = route('bookmarks.index');
        $bookmarkLinks = $xpath->query('//a[@href="'.$bookmarkUrl.'"]');

        $this->assertSame(1, $bookmarkLinks->length, 'Saved should be one responsive navigation item, not duplicated markup.');
        $bookmarkLink = $bookmarkLinks->item(0);
        if (! ($bookmarkLink instanceof \DOMElement)) {
            throw new \LogicException('Expected the responsive Saved link to be an HTML element.');
        }
        $this->assertSame('saved', $bookmarkLink->getAttribute('data-nav-key'));
        $this->assertSame('desktop-primary mobile-secondary', $bookmarkLink->getAttribute('data-nav-surfaces'));

        $savedLabel = __('navigation.saved');
        $explicitAriaLabels = 0;
        foreach ($bookmarkLinks as $bookmarkLink) {
            $visibleLabel = trim(preg_replace('/\\s+/', ' ', $bookmarkLink->textContent));
            $ariaLabel = trim($bookmarkLink->getAttribute('aria-label'));
            $accessibleLabel = $ariaLabel !== '' ? $ariaLabel : $visibleLabel;

            $this->assertSame($savedLabel, $accessibleLabel);
            $this->assertNotSame('', $visibleLabel, 'Each bookmark link should have a visible accessible name.');
            $explicitAriaLabels += $ariaLabel !== '' ? 1 : 0;
        }
        $this->assertSame(1, $explicitAriaLabels, 'The sidebar link should retain its explicit accessible label.');
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        return Post::query()->create(array_merge([
            'user_id' => $user->id,
            'content' => 'A bookmarkable post',
        ], $attributes));
    }

    private function createBookmark(User $user, Post $post, array $attributes = []): Bookmark
    {
        return Bookmark::query()->create(array_merge([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ], $attributes));
    }
}
