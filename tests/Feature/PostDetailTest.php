<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These assertions remain the Blade fallback contract; the Inertia contract is covered separately.
        config(['frontend.post_detail_inertia' => false]);
    }

    public function test_authenticated_user_can_open_post_detail(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Detail content']);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));

        $response->assertOk();
        $this->assertSame($post->id, $response->viewData('post')->id);
    }

    public function test_guest_is_redirected_to_login_from_post_detail(): void
    {
        $post = $this->createPost(User::factory()->create());

        $this->get(route('posts.show', $post))
            ->assertRedirect(route('login'));
    }

    public function test_invalid_uid_returns_not_found_for_authenticated_user(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/post/missing-post-uid')
            ->assertNotFound();
    }

    public function test_post_detail_uses_uid_binding_and_renders_author_and_content(): void
    {
        $author = User::factory()->create(['username' => 'detail-author']);
        $viewer = User::factory()->create();
        $post = $this->createPost($author, ['content' => 'Visible detail content']);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));

        $this->assertSame('uid', $post->getRouteKeyName());
        $this->assertStringContainsString('/post/'.$post->uid, route('posts.show', $post));
        $response->assertSee('detail-author')
            ->assertSee('Visible detail content');
    }

    public function test_post_detail_exposes_scalar_post_counts_and_interaction_state(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->createPost($author);

        Like::create(['post_id' => $post->id, 'user_id' => $viewer->id]);
        Like::create(['post_id' => $post->id, 'user_id' => $other->id]);
        $root = $this->createComment($post, $other, 'Root detail comment');
        $this->createComment($post, $viewer, 'Reply detail comment', ['parent_id' => $root->id]);
        $this->createPost($other, ['repost_id' => $post->id, 'content' => null]);
        $this->createPost($viewer, ['repost_id' => $post->id, 'content' => null]);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $displayPost = $response->viewData('displayPost');

        $this->assertSame(2, $displayPost->likes_count);
        $this->assertSame(2, $displayPost->comments_count);
        $this->assertSame(2, $displayPost->reposts_count);
        $this->assertTrue((bool) $displayPost->liked_by_current_user);
        $this->assertTrue((bool) $displayPost->reposted_by_current_user);
        $this->assertFalse($displayPost->relationLoaded('likes'));
        $response->assertSee('Root detail comment');
    }

    public function test_repost_detail_uses_original_content_author_and_preserves_actor(): void
    {
        $originalAuthor = User::factory()->create(['username' => 'original-detail-author']);
        $reposter = User::factory()->create(['username' => 'detail-reposter']);
        $viewer = User::factory()->create();
        $original = $this->createPost($originalAuthor, ['content' => 'Original detail content']);
        $repost = $this->createPost($reposter, [
            'repost_id' => $original->id,
            'content' => null,
        ]);

        $response = $this->actingAs($viewer)->get(route('posts.show', $repost));

        $this->assertSame($original->id, $response->viewData('displayPost')->id);
        $response->assertSee('Original detail content')
            ->assertSee('original-detail-author')
            ->assertSee('detail-reposter');
    }

    public function test_root_comments_are_paginated_separately_from_all_comment_count(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($number = 1; $number <= 21; $number++) {
            $root = $this->createComment($post, $author, "Root {$number}");
            $this->createComment($post, $viewer, "Reply {$number}", ['parent_id' => $root->id]);
        }

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $comments = $response->viewData('comments');
        $displayPost = $response->viewData('displayPost');

        $this->assertInstanceOf(Paginator::class, $comments);
        $this->assertCount(20, $comments->items());
        $this->assertFalse($comments->getCollection()->contains(fn (Comment $comment): bool => $comment->parent_id !== null));
        $this->assertSame(42, $displayPost->comments_count);
        $this->assertTrue($comments->hasMorePages());
    }

    public function test_root_comments_use_deterministic_created_at_and_id_ordering(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $sameTime = Carbon::create(2026, 9, 1, 12, 0, 0);

        $older = $this->createComment($post, $author, 'Older root', [
            'created_at' => $sameTime->copy()->subMinute(),
            'updated_at' => $sameTime->copy()->subMinute(),
        ]);
        $sameTimeLowerId = $this->createComment($post, $author, 'Same time lower id', [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);
        $sameTimeHigherId = $this->createComment($post, $author, 'Same time higher id', [
            'created_at' => $sameTime,
            'updated_at' => $sameTime,
        ]);

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');

        $this->assertSame(
            [$sameTimeHigherId->id, $sameTimeLowerId->id, $older->id],
            $comments->getCollection()->pluck('id')->all(),
        );
    }

    public function test_replies_are_bounded_ordered_and_include_scalar_like_state_and_users(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $replyAuthor = User::factory()->create(['username' => 'reply-detail-author']);
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Root with replies');
        $sameTime = Carbon::create(2026, 9, 1, 12, 0, 0);

        $this->createComment($post, $replyAuthor, 'Reply one', [
            'parent_id' => $root->id,
            'created_at' => $sameTime->copy()->addSecond(),
            'updated_at' => $sameTime->copy()->addSecond(),
        ]);
        $replyTwo = $this->createComment($post, $replyAuthor, 'Reply two', [
            'parent_id' => $root->id,
            'created_at' => $sameTime->copy()->addSeconds(2),
            'updated_at' => $sameTime->copy()->addSeconds(2),
        ]);
        $replyThree = $this->createComment($post, $replyAuthor, 'Reply three', [
            'parent_id' => $root->id,
            'created_at' => $sameTime->copy()->addSeconds(3),
            'updated_at' => $sameTime->copy()->addSeconds(3),
        ]);
        $replyTwo->likes()->attach($viewer->id);

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');
        $loadedRoot = $comments->first();

        $this->assertCount(2, $loadedRoot->feedReplies);
        $this->assertSame([$replyThree->id, $replyTwo->id], $loadedRoot->feedReplies->pluck('id')->all());
        foreach ($loadedRoot->feedReplies as $reply) {
            $this->assertTrue($reply->relationLoaded('user'));
            $this->assertArrayHasKey('likes_count', $reply->getAttributes());
            $this->assertArrayHasKey('liked_by_current_user', $reply->getAttributes());
            $this->assertFalse($reply->relationLoaded('likes'));
        }
        $this->assertSame('reply-detail-author', $loadedRoot->feedReplies->first()->user->username);
    }

    public function test_reply_limit_is_applied_per_root_comment(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($rootNumber = 1; $rootNumber <= 2; $rootNumber++) {
            $root = $this->createComment($post, $author, "Multiple root {$rootNumber}");

            for ($replyNumber = 1; $replyNumber <= 3; $replyNumber++) {
                $this->createComment($post, $viewer, "Root {$rootNumber} reply {$replyNumber}", [
                    'parent_id' => $root->id,
                ]);
            }
        }

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');

        $this->assertCount(2, $comments->items());
        foreach ($comments as $root) {
            $this->assertTrue($root->relationLoaded('feedReplies'));
            $this->assertCount(2, $root->feedReplies);
        }
    }

    public function test_root_with_two_replies_renders_every_reply_without_an_expansion_link(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create(['locale' => 'id']);
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Root with complete preview');
        $this->createComment($post, $viewer, 'Reachable reply one', ['parent_id' => $root->id]);
        $this->createComment($post, $viewer, 'Reachable reply two', ['parent_id' => $root->id]);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $loadedRoot = $response->viewData('comments')->first();

        $response->assertOk()
            ->assertSee('Reachable reply one')
            ->assertSee('Reachable reply two')
            ->assertDontSee('Lihat balasan lainnya');
        $this->assertSame(2, $loadedRoot->replies_count);
        $this->assertCount(2, $loadedRoot->feedReplies);
    }

    public function test_later_replies_are_reachable_through_bounded_stable_cursor_urls(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create(['locale' => 'en']);
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Root with paginated replies');
        $sameTime = Carbon::create(2026, 9, 1, 12, 0, 0);
        $replies = collect();

        for ($number = 1; $number <= 12; $number++) {
            $replies->push($this->createComment($post, $viewer, "Expanded reply {$number}", [
                'parent_id' => $root->id,
                'created_at' => $sameTime,
                'updated_at' => $sameTime,
            ]));
        }

        $initialResponse = $this->actingAs($viewer)->get(route('posts.show', $post));
        $initialRoot = $initialResponse->viewData('comments')->first();
        $expansionUrl = route('posts.show', [
            'post' => $post,
            'comments_page' => 1,
            'replies_for' => $root->id,
        ]);

        $this->assertSame(12, $initialRoot->replies_count);
        $this->assertSame(
            $replies->reverse()->take(2)->pluck('id')->all(),
            $initialRoot->feedReplies->pluck('id')->all(),
        );
        $initialResponse->assertSee('View more replies')
            ->assertSee('href="'.e($expansionUrl.'#comment-'.$root->id).'"', false);

        $firstExpandedResponse = $this->actingAs($viewer)->get($expansionUrl);
        $firstExpandedReplies = $firstExpandedResponse->viewData('expandedReplies');
        $firstExpandedIds = $firstExpandedReplies->getCollection()->pluck('id')->all();

        $this->assertInstanceOf(CursorPaginator::class, $firstExpandedReplies);
        $this->assertSame($root->id, $firstExpandedResponse->viewData('expandedReplyRootId'));
        $this->assertCount(10, $firstExpandedReplies->getCollection());
        $this->assertSame($replies->reverse()->take(10)->pluck('id')->all(), $firstExpandedIds);
        $this->assertTrue($firstExpandedReplies->hasMorePages());
        $firstExpandedResponse->assertSee('Expanded reply 3');
        $this->assertSame(1, substr_count($firstExpandedResponse->getContent(), 'Expanded reply 12'));

        foreach ($firstExpandedReplies as $reply) {
            $this->assertTrue($reply->relationLoaded('user'));
            $this->assertArrayHasKey('likes_count', $reply->getAttributes());
            $this->assertArrayHasKey('liked_by_current_user', $reply->getAttributes());
            $this->assertFalse($reply->relationLoaded('likes'));
        }

        $nextPageUrl = $firstExpandedReplies->nextPageUrl();

        $this->assertNotNull($nextPageUrl);
        $this->assertStringContainsString('replies_cursor=', $nextPageUrl);
        $this->assertStringContainsString('comments_page=1', $nextPageUrl);
        $this->assertStringContainsString('replies_for='.$root->id, $nextPageUrl);
        $firstExpandedResponse->assertSee(
            'href="'.e($nextPageUrl.'#comment-'.$root->id).'"',
            false,
        );

        $secondExpandedResponse = $this->actingAs($viewer)->get($nextPageUrl);
        $secondExpandedReplies = $secondExpandedResponse->viewData('expandedReplies');
        $secondExpandedIds = $secondExpandedReplies->getCollection()->pluck('id')->all();

        $this->assertSame($replies->take(2)->reverse()->values()->pluck('id')->all(), $secondExpandedIds);
        $this->assertSame([], array_values(array_intersect($firstExpandedIds, $secondExpandedIds)));
        $this->assertFalse($secondExpandedReplies->hasMorePages());
        $secondExpandedResponse->assertSee('Expanded reply 1');
    }

    public function test_stale_reply_url_redirects_to_the_roots_current_page_after_page_drift(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $baseTime = Carbon::create(2026, 9, 1, 12, 0, 0);
        $selectedRoot = $this->createComment($post, $author, 'Drift selected root', [
            'created_at' => $baseTime,
            'updated_at' => $baseTime,
        ]);

        for ($number = 1; $number <= 19; $number++) {
            $this->createComment($post, $author, "Newer root {$number}", [
                'created_at' => $baseTime->copy()->addSeconds($number),
                'updated_at' => $baseTime->copy()->addSeconds($number),
            ]);
        }

        for ($number = 1; $number <= 11; $number++) {
            $this->createComment($post, $viewer, sprintf('Drift reply %02d', $number), [
                'parent_id' => $selectedRoot->id,
                'created_at' => $baseTime->copy()->addMinutes($number),
                'updated_at' => $baseTime->copy()->addMinutes($number),
            ]);
        }

        $initialExpansionUrl = route('posts.show', [
            'post' => $post,
            'comments_page' => 1,
            'replies_for' => $selectedRoot->id,
        ]);
        $initialResponse = $this->actingAs($viewer)->get($initialExpansionUrl);
        $initialReplies = $initialResponse->viewData('expandedReplies');
        $staleReplyUrl = $initialReplies->nextPageUrl();

        $initialResponse->assertOk();
        $this->assertSame($selectedRoot->id, $initialResponse->viewData('expandedReplyRootId'));
        $this->assertNotNull($staleReplyUrl);

        parse_str((string) parse_url($staleReplyUrl, PHP_URL_QUERY), $staleQuery);
        $this->assertSame('1', $staleQuery['comments_page']);
        $this->assertArrayHasKey('replies_cursor', $staleQuery);

        $this->createComment($post, $author, 'Root that causes page drift', [
            'created_at' => $baseTime->copy()->addSeconds(20),
            'updated_at' => $baseTime->copy()->addSeconds(20),
        ]);

        $canonicalParameters = [
            'post' => $post,
            'comments_page' => 2,
            'replies_for' => $selectedRoot->id,
            'replies_cursor' => $staleQuery['replies_cursor'],
        ];
        $canonicalUrl = route('posts.show', $canonicalParameters);

        $this->actingAs($viewer)
            ->get($staleReplyUrl)
            ->assertRedirect($canonicalUrl.'#comment-'.$selectedRoot->id);

        $correctedResponse = $this->actingAs($viewer)->get($canonicalUrl);
        $correctedReplies = $correctedResponse->viewData('expandedReplies');

        $correctedResponse->assertOk()
            ->assertSee('Drift selected root')
            ->assertSee('Drift reply 01');
        $this->assertSame(2, $correctedResponse->viewData('comments')->currentPage());
        $this->assertSame($selectedRoot->id, $correctedResponse->viewData('expandedReplyRootId'));
        $this->assertCount(1, $correctedReplies->getCollection());
    }

    public function test_reply_expansion_action_is_localized_for_supported_account_locales(): void
    {
        $author = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Localized reply root');

        for ($number = 1; $number <= 3; $number++) {
            $this->createComment($post, $author, "Localized reply {$number}", [
                'parent_id' => $root->id,
            ]);
        }

        foreach ([
            'id' => 'Lihat balasan lainnya',
            'en' => 'View more replies',
        ] as $locale => $copy) {
            $viewer = User::factory()->create(['locale' => $locale]);

            $this->actingAs($viewer)
                ->get(route('posts.show', $post))
                ->assertOk()
                ->assertSee($copy);
        }
    }

    public function test_reply_expansion_rejects_a_root_comment_from_another_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $this->createComment($post, $author, 'Local root');
        $foreignPost = $this->createPost($author);
        $foreignRoot = $this->createComment($foreignPost, $author, 'Foreign root');
        $this->createComment($foreignPost, $author, 'Foreign reply secret', [
            'parent_id' => $foreignRoot->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('posts.show', [
                'post' => $post,
                'comments_page' => 1,
                'replies_for' => $foreignRoot->id,
            ]))
            ->assertNotFound()
            ->assertDontSee('Foreign reply secret');
    }

    public function test_reply_expansion_rejects_a_child_comment_as_the_selected_root(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Valid root');
        $child = $this->createComment($post, $author, 'Child cannot be selected as root', [
            'parent_id' => $root->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('posts.show', [
                'post' => $post,
                'comments_page' => 1,
                'replies_for' => $child->id,
            ]))
            ->assertNotFound();
    }

    public function test_reply_expansion_returns_not_found_after_the_selected_root_is_deleted(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Root deleted after link creation');
        $url = route('posts.show', [
            'post' => $post,
            'comments_page' => 1,
            'replies_for' => $root->id,
        ]);

        $root->delete();

        $this->actingAs($viewer)
            ->get($url)
            ->assertNotFound();
    }

    public function test_root_and_reply_like_counts_and_current_user_state_are_scalar(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->createPost($author);
        $root = $this->createComment($post, $author, 'Liked root');
        $reply = $this->createComment($post, $other, 'Liked reply', ['parent_id' => $root->id]);

        $root->likes()->attach([$viewer->id, $other->id]);
        $reply->likes()->attach($viewer->id);

        $comments = $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->viewData('comments');
        $loadedRoot = $comments->first();
        $loadedReply = $loadedRoot->feedReplies->first();

        $this->assertSame(2, $loadedRoot->likes_count);
        $this->assertTrue((bool) $loadedRoot->liked_by_current_user);
        $this->assertSame(1, $loadedReply->likes_count);
        $this->assertTrue((bool) $loadedReply->liked_by_current_user);
        $this->assertFalse($loadedRoot->relationLoaded('likes'));
        $this->assertFalse($loadedReply->relationLoaded('likes'));
    }

    public function test_detail_does_not_issue_per_comment_or_reply_like_relation_queries(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($number = 1; $number <= 3; $number++) {
            $root = $this->createComment($post, $author, "Query root {$number}");
            $this->createComment($post, $viewer, "Query reply {$number}", ['parent_id' => $root->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('posts.show', $post));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $perCommentLikeQueries = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower(trim($query['query']));

            return str_starts_with($sql, 'select "users".')
                && str_contains($sql, 'from "users" inner join "comment_likes"');
        });

        $this->assertCount(0, $perCommentLikeQueries);
    }

    public function test_detail_with_more_than_one_comment_page_still_renders_first_page(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->createPost($author);

        for ($number = 1; $number <= 21; $number++) {
            $this->createComment($post, $author, "Volume root {$number}");
        }

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $comments = $response->viewData('comments');

        $response->assertOk();
        $this->assertCount(20, $comments->items());
        $this->assertTrue($comments->hasMorePages());

        $secondPage = $this->actingAs($viewer)
            ->get(route('posts.show', $post).'?comments_page=2');
        $secondPageComments = $secondPage->viewData('comments');

        $secondPage->assertOk()->assertSee('Volume root 1');
        $this->assertSame('Volume root 1', $secondPageComments->first()->content);
    }

    private function createPost(User $user, array $attributes = []): Post
    {
        $post = Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Detail post',
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
}
