<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
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
        $this->assertStringContainsString('body: JSON.stringify({ liked: desiredLiked })', $javascript);
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
            ->assertSee('class="skuy-upload"', false)
            ->assertSee('selectedImages', false)
            ->assertSee('x-ref="imagesInput"', false)
            ->assertSee('@change="handleImageSelection($event)"', false)
            ->assertSee('new DataTransfer()', false)
            ->assertSee('URL.createObjectURL(file)', false)
            ->assertSee('@click="openLightbox(selectedImages.map(item => item.previewUrl), index)"', false)
            ->assertSee(':aria-label="\'Lihat pratinjau gambar \' + (index + 1)"', false)
            ->assertSee('@click="closeImageIfActive(image.previewUrl); removeImage(index)"', false)
            ->assertSee('openLightbox(images, index = 0)', false)
            ->assertSee('lightboxImages.length > 1', false)
            ->assertSee('@click.stop="previousImage()"', false)
            ->assertSee('@click.stop="nextImage()"', false)
            ->assertSee('$event.key === \'ArrowLeft\'', false)
            ->assertSee('$event.key === \'ArrowRight\'', false)
            ->assertSee('aria-label="Gambar sebelumnya"', false)
            ->assertSee('aria-label="Gambar selanjutnya"', false)
            ->assertSee('@keydown.escape.window="closeImage()"', false)
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
            '/\.skuy-action--dense-count\s+svg\s*\{\s*width:\s*14px;\s*height:\s*14px;\s*\}/s',
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
        $this->assertStringContainsString(
            'availableTopics: [&quot;alpha&quot;,&quot;middle&quot;,&quot;zeta&quot;]',
            $response->getContent(),
        );
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
