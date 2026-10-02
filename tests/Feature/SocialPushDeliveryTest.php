<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Enums\OneSignalPushOutcome;
use App\Jobs\SendSocialPushNotification;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentPosted;
use App\Notifications\PostLiked;
use App\Notifications\UserFollowed;
use App\Services\OneSignalPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SocialPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://skuysocial.test',
            'services.onesignal' => [
                'enabled' => true,
                'app_id' => '11111111-1111-4111-8111-111111111111',
                'rest_api_key' => 'synthetic-g2-test-key',
            ],
        ]);
    }

    public function test_rolled_back_social_action_does_not_leave_a_deferred_push(): void
    {
        $actor = User::factory()->create(['name' => 'Ari Actor']);
        $recipient = User::factory()->create(['locale' => 'en']);
        $recipient->forceFill(['locale' => 'en'])->save();
        $post = Post::create(['user_id' => $recipient->id, 'content' => 'Private content']);
        $this->configureSuccessfulOneSignal();
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic'], 200)]);

        try {
            DB::transaction(function () use ($recipient, $actor, $post): void {
                SendSocialPushNotification::dispatchAfterCommit(
                    $recipient->id,
                    SendSocialPushNotification::LIKE,
                    $actor->id,
                    $actor->name,
                    (string) $post->uid,
                );

                throw new RuntimeException('Force the social action transaction to roll back.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force the social action transaction to roll back.', $exception->getMessage());
        }

        app(DeferredCallbackCollection::class)->invokeWhen();

        Http::assertNothingSent();
    }

    public function test_new_like_dispatches_one_deferred_job_only_for_new_non_self_like(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create(['name' => 'Ari Actor']);
        $post = Post::create(['user_id' => $owner->id, 'content' => 'PRIVATE POST CONTENT']);
        $owner->forceFill(['locale' => 'en'])->save();
        $this->actingAs($actor)->postJson(route('posts.like', $post), ['liked' => true])->assertOk();
        $job = Queue::pushed(SendSocialPushNotification::class)->first();
        $this->assertNotNull($job);
        $this->assertSame($owner->id, $job->recipientId);
        $this->assertSame($actor->id, $job->actorId);
        $this->assertSame(SendSocialPushNotification::LIKE, $job->event);
        $this->assertSame((string) $post->uid, $job->postUid);
        $this->assertSame('deferred', $job->connection);
        $this->assertTrue($job->afterCommit);
        $this->assertSame(1, $owner->notifications()->where('type', PostLiked::class)->count());
        $this->assertSame(0, $actor->notifications()->count());

        Queue::fake();
        $this->actingAs($actor)->postJson(route('posts.like', $post), ['liked' => true])->assertOk();
        Queue::assertNothingPushed();

        Queue::fake();
        $this->actingAs($actor)->postJson(route('posts.like', $post), ['liked' => false])->assertOk();
        Queue::assertNothingPushed();

        Queue::fake();
        $this->actingAs($owner)->postJson(route('posts.like', $post), ['liked' => true])->assertOk();
        Queue::assertNothingPushed();
    }

    public function test_follow_dispatch_is_deferred_and_after_commit(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $recipient = User::factory()->create(['locale' => 'en']);

        $this->actingAs($actor)->post(route('user.follow', $recipient))->assertRedirect();

        Queue::assertPushed(SendSocialPushNotification::class, function (SendSocialPushNotification $job) use ($recipient, $actor): bool {
            return $job->recipientId === $recipient->id
                && $job->actorId === $actor->id
                && $job->event === SendSocialPushNotification::FOLLOW
                && $job->connection === 'deferred'
                && $job->afterCommit === true;
        });

        $this->assertSame(1, $recipient->notifications()->where('type', UserFollowed::class)->count());

        Queue::fake();
        $this->actingAs($actor)->post(route('user.follow', $recipient))->assertRedirect();
        Queue::assertNothingPushed();
    }

    public function test_new_top_level_comment_pushes_without_comment_text_and_suppresses_self_comment(): void
    {
        Queue::fake();
        $actor = User::factory()->create(['name' => 'Comment Actor']);
        $actor->forceFill(['username' => 'g2-comment-actor-test'])->save();
        $owner = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'content' => 'PRIVATE POST CONTENT']);

        $this->actingAs($actor)->postJson(route('comments.store', $post), ['content' => 'PRIVATE COMMENT BODY'])->assertCreated();
        $job = Queue::pushed(SendSocialPushNotification::class)->first();
        $this->assertNotNull($job);
        $this->assertSame($owner->id, $job->recipientId);
        $this->assertSame(SendSocialPushNotification::COMMENT, $job->event);
        $this->assertSame((string) $post->uid, $job->postUid);
        $this->assertSame(1, $owner->notifications()->where('type', CommentPosted::class)->count());
        $this->assertSame(0, $actor->notifications()->count());
        Queue::fake();
        $this->actingAs($owner)->postJson(route('comments.store', $post), ['content' => 'SELF COMMENT BODY'])->assertCreated();
        Queue::assertNothingPushed();
    }

    public function test_reply_notifies_post_owner_and_parent_author_once_with_post_deep_link(): void
    {
        Queue::fake();
        $postOwner = User::factory()->create();
        $rootAuthor = User::factory()->create(['locale' => 'en']);
        $actor = User::factory()->create(['name' => 'Safe Actor']);
        $post = Post::create(['user_id' => $postOwner->id, 'content' => 'PRIVATE POST']);
        $root = Comment::query()->create(['post_id' => $post->id, 'user_id' => $rootAuthor->id, 'content' => 'PRIVATE ROOT']);

        $this->actingAs($actor)->postJson(route('comments.store', $post), [
            'content' => 'PRIVATE REPLY',
            'parent_id' => $root->id,
        ])->assertCreated();

        $this->assertSame(1, $postOwner->notifications()->where('type', CommentPosted::class)->count());
        $this->assertSame(1, $rootAuthor->notifications()->where('type', CommentPosted::class)->count());
        $ownerActivity = $postOwner->notifications()->where('type', CommentPosted::class)->firstOrFail();
        $replyActivity = $rootAuthor->notifications()->where('type', CommentPosted::class)->firstOrFail();
        $this->assertSame('comment', $ownerActivity->data['type']);
        $this->assertSame('reply', $replyActivity->data['type']);
        $this->assertSame($actor->id, $replyActivity->data['user_id']);
        $this->assertSame((string) $post->uid, $replyActivity->data['post_slug']);
        $this->assertSame((int) $root->id, (int) $replyActivity->data['parent_id']);
        $this->assertArrayNotHasKey('post_url', $replyActivity->data);
        $this->assertSame(0, $actor->notifications()->count());
        $jobs = Queue::pushed(SendSocialPushNotification::class);
        $this->assertCount(2, $jobs);
        $this->assertEqualsCanonicalizing([$postOwner->id, $rootAuthor->id], array_map(static fn (SendSocialPushNotification $job): int => $job->recipientId, $jobs->all()));
        foreach ($jobs as $job) {
            $this->assertSame(SendSocialPushNotification::REPLY, $job->event);
            $this->assertSame((int) $root->id, $job->parentCommentId);
        }

        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-push-id'], 200)]);
        foreach ($jobs as $job) {
            $job->handle(app(OneSignalPushService::class));
        }
        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $expectedParentUrl = 'https://skuysocial.test'.route('posts.show', $post->uid, false).'#comment-'.$root->id;
        $this->assertSame(
            1,
            $requests->filter(static fn (array $request): bool => $request[0]->data()['url'] === $expectedParentUrl)->count(),
        );
        $this->assertStringNotContainsString('PRIVATE REPLY', json_encode($requests->all(), JSON_THROW_ON_ERROR));
    }

    public function test_reply_deduplicates_post_owner_and_parent_author_as_one_recipient(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'content' => 'PRIVATE POST']);
        $root = Comment::query()->create(['post_id' => $post->id, 'user_id' => $owner->id, 'content' => 'PRIVATE ROOT']);
        $this->actingAs($actor)->postJson(route('comments.store', $post), ['content' => 'PRIVATE REPLY', 'parent_id' => $root->id])->assertCreated();
        $this->assertCount(1, Queue::pushed(SendSocialPushNotification::class));
        $this->assertSame(1, $owner->notifications()->where('type', CommentPosted::class)->count());
    }

    public function test_reply_by_parent_author_creates_no_self_activity_but_notifies_post_owner(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'content' => 'PRIVATE POST']);
        $root = Comment::query()->create(['post_id' => $post->id, 'user_id' => $actor->id, 'content' => 'PRIVATE ROOT']);
        $this->actingAs($actor)->postJson(route('comments.store', $post), ['content' => 'PRIVATE REPLY', 'parent_id' => $root->id])->assertCreated();
        $this->assertSame(0, $actor->notifications()->count());
        $this->assertSame(1, $owner->notifications()->where('type', CommentPosted::class)->count());
        $this->assertCount(1, Queue::pushed(SendSocialPushNotification::class));
    }

    public function test_new_follow_pushes_once_to_followed_user_and_unfollow_does_not(): void
    {
        Queue::fake();
        $actor = User::factory()->create();
        $recipient = User::factory()->create(['locale' => 'en']);

        $this->actingAs($actor)->post(route('user.follow', $recipient))->assertRedirect();
        Queue::assertPushed(SendSocialPushNotification::class, function (SendSocialPushNotification $job) use ($actor, $recipient): bool {
            return $job->recipientId === $recipient->id
                && $job->actorId === $actor->id
                && $job->event === SendSocialPushNotification::FOLLOW
                && $job->connection === 'deferred'
                && $job->afterCommit === true;
        });
        $this->assertSame(1, $recipient->notifications()->where('type', UserFollowed::class)->count());
        $this->post(route('user.follow', $recipient))->assertRedirect();
        $this->post(route('user.follow', $recipient))->assertRedirect();
        Queue::assertPushed(SendSocialPushNotification::class, 2);
    }

    public function test_accepted_dm_pushes_to_counterpart_and_denied_dm_does_not(): void
    {
        Queue::fake();
        $sender = User::factory()->create();
        $recipient = User::factory()->create(['locale' => 'en']);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($sender, $recipient);

        $this->actingAs($sender)->postJson(route('messages.store', $conversation), ['body' => 'PRIVATE DM BODY'])->assertCreated();
        Queue::assertPushed(SendSocialPushNotification::class, function (SendSocialPushNotification $job) use ($sender, $recipient, $conversation): bool {
            return $job->recipientId === $recipient->id
                && $job->actorId === $sender->id
                && $job->event === SendSocialPushNotification::DIRECT_MESSAGE
                && $job->conversationId === $conversation->id
                && $job->connection === 'deferred'
                && $job->afterCommit === true;
        });
        $this->assertSame(0, $recipient->notifications()->count());

        $recipient->forceFill(['message_privacy' => 'nobody'])->save();
        $this->postJson(route('messages.store', $conversation), ['body' => 'DENIED PRIVATE DM BODY'])->assertForbidden();
        Queue::assertPushed(SendSocialPushNotification::class, 1);
    }

    public function test_job_uses_recipient_locale_and_canonical_post_link_without_sensitive_content(): void
    {
        Queue::fake();
        $actor = User::factory()->create([
            'name' => 'Safe Actor',
            'email' => 'private@example.test',
        ]);
        $recipient = User::factory()->create(['locale' => 'en']);
        $post = Post::create(['user_id' => $recipient->id, 'content' => 'PRIVATE POST CONTENT']);
        $this->configureSuccessfulOneSignal();
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-push-id'], 200)]);

        (new SendSocialPushNotification(
            recipientId: $recipient->id,
            event: SendSocialPushNotification::LIKE,
            actorId: $actor->id,
            actorName: $actor->name,
            postUid: (string) $post->uid,
        ))->handle(app(OneSignalPushService::class));

        $recorded = Http::recorded()->last();
        $this->assertNotNull($recorded, 'No HTTP request recorded for recipient-locale push.');
        $payload = $recorded[0]->data();
        $this->assertSame('https://skuysocial.test'.route('posts.show', $post->uid, false), $payload['url'] ?? null);
        $this->assertSame('Safe Actor liked your thread.', $payload['contents']['en'] ?? null);
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('private@example.test', $serialized);
        $this->assertStringNotContainsString('PRIVATE POST CONTENT', $serialized);
    }

    public function test_all_events_use_recipient_locale_and_canonical_destination(): void
    {
        $actor = User::factory()->create(['name' => 'Safe Actor']);
        $recipient = User::factory()->create(['locale' => 'en']);
        config(['app.locale' => 'en']);
        $post = Post::create(['user_id' => $recipient->id, 'content' => 'PRIVATE POST']);
        $root = Comment::query()->create(['post_id' => $post->id, 'user_id' => $actor->id, 'content' => 'PRIVATE ROOT']);
        $conversation = app(FindOrCreateDirectConversation::class)->handle($actor, $recipient);
        $this->configureSuccessfulOneSignal();

        $cases = [
            [SendSocialPushNotification::LIKE, route('posts.show', $post->uid, false), 'Safe Actor liked your thread.', 'Safe Actor menyukai utas Anda.', 'en'],
            [SendSocialPushNotification::COMMENT, route('posts.show', $post->uid, false), 'Safe Actor commented on your thread.', 'Safe Actor mengomentari utas Anda.', 'id'],
            [SendSocialPushNotification::REPLY, route('posts.show', $post->uid, false), 'Safe Actor replied to a comment on your thread.', 'Safe Actor membalas komentar di utas Anda.', 'en'],
            [SendSocialPushNotification::FOLLOW, route('user.profile', $actor->username, false), 'Safe Actor started following you.', 'Safe Actor mulai mengikuti Anda.', 'id'],
            [SendSocialPushNotification::DIRECT_MESSAGE, route('messages.show', $conversation, false), 'New message from Safe Actor.', 'Pesan baru dari Safe Actor.', 'en'],
        ];

        foreach ($cases as [$event, $path, $english, $indonesian, $locale]) {
            Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-push-id'], 200)]);
            $this->configureSuccessfulOneSignal();
            $recipient->forceFill(['locale' => $locale])->save();
            config(['app.locale' => $locale]);
            (new SendSocialPushNotification(
                recipientId: $recipient->id,
                event: $event,
                actorId: $actor->id,
                actorName: $actor->name,
                postUid: in_array($event, [SendSocialPushNotification::LIKE, SendSocialPushNotification::COMMENT, SendSocialPushNotification::REPLY], true) ? (string) $post->uid : null,
                conversationId: $event === SendSocialPushNotification::DIRECT_MESSAGE ? (int) $conversation->id : null,
                parentCommentId: $event === SendSocialPushNotification::REPLY ? (int) $root->id : null,
            ))->handle(app(OneSignalPushService::class));

            $recorded = Http::recorded()->last();
            $this->assertNotNull($recorded, 'No request recorded for '.$event.' push.');
            $this->assertSame(
                'https://skuysocial.test'.$path,
                $recorded[0]->data()['url'] ?? null,
            );
            $copy = $locale === 'en' ? $english : $indonesian;
            $this->assertSame($copy, $recorded[0]->data()['contents']['en'] ?? null);
            $this->assertSame((string) trans('push.social.heading', [], $locale), $recorded[0]->data()['headings']['en'] ?? null);
            if ($locale !== 'en') {
                $this->assertSame($copy, $recorded[0]->data()['contents'][$locale] ?? null);
                $this->assertSame((string) trans('push.social.heading', [], $locale), $recorded[0]->data()['headings'][$locale] ?? null);
            }
        }
    }

    public function test_missing_self_suspended_and_banned_are_suppressed_but_restricted_is_allowed(): void
    {
        $actor = User::factory()->create(['name' => 'Safe Actor']);
        $this->configureSuccessfulOneSignal();
        config(['app.locale' => 'en']);
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-push-id'], 200)]);

        foreach (['suspended', 'banned'] as $state) {
            $recipient = User::factory()->create(['moderation_state' => $state]);
            (new SendSocialPushNotification($recipient->id, SendSocialPushNotification::FOLLOW, $actor->id, $actor->name))->handle(app(OneSignalPushService::class));
        }
        (new SendSocialPushNotification($actor->id, SendSocialPushNotification::FOLLOW, $actor->id, $actor->name))->handle(app(OneSignalPushService::class));
        (new SendSocialPushNotification(99999999, SendSocialPushNotification::FOLLOW, $actor->id, $actor->name))->handle(app(OneSignalPushService::class));
        $this->assertCount(0, Http::recorded());

        $restricted = User::factory()->create();
        $restricted->forceFill(['moderation_state' => 'restricted', 'locale' => 'id'])->save();
        config(['app.locale' => 'id']);
        (new SendSocialPushNotification($restricted->id, SendSocialPushNotification::FOLLOW, $actor->id, $actor->name))->handle(app(OneSignalPushService::class));
        $recorded = Http::recorded()->last();
        $this->assertNotNull($recorded, 'No HTTP request recorded for recipient-locale push.');
        $this->assertEqualsCanonicalizing(
            [
                'en' => 'Safe Actor mulai mengikuti Anda.',
                'id' => 'Safe Actor mulai mengikuti Anda.',
            ],
            $recorded[0]->data()['contents'] ?? null,
        );
    }

    public function test_unknown_or_null_moderation_state_is_suppressed(): void
    {
        $actor = User::factory()->create(['name' => 'Safe Actor']);
        $recipients = [
            User::factory()->create(),
            User::factory()->create(),
        ];
        $this->configureSuccessfulOneSignal();
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-push-id'], 200)]);

        $targetRecipientId = null;
        $injectedState = null;
        User::retrieved(static function (User $user) use (&$targetRecipientId, &$injectedState): void {
            if ((int) $user->getKey() === $targetRecipientId) {
                $user->setAttribute('moderation_state', $injectedState);
            }
        });

        foreach ($recipients as $index => $recipient) {
            // Simulate malformed persisted values without violating the DB's moderation constraint.
            $targetRecipientId = $recipient->getKey();
            $injectedState = $index === 0 ? null : 'unrecognized-state';
            (new SendSocialPushNotification(
                $recipient->id,
                SendSocialPushNotification::FOLLOW,
                $actor->id,
                $actor->name,
            ))->handle(app(OneSignalPushService::class));
        }

        Http::assertNothingSent();
    }

    public function test_unconfigured_or_provider_failure_does_not_break_like_mutation(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'content' => 'PRIVATE POST']);
        config(['services.onesignal.enabled' => false]);
        $this->actingAs($actor)->post(route('posts.like', $post))->assertRedirect();
        $this->assertDatabaseHas('likes', ['post_id' => $post->id, 'user_id' => $actor->id]);

        config(['services.onesignal.enabled' => true]);
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['errors' => ['failure']], 503)]);
        (new SendSocialPushNotification($owner->id, SendSocialPushNotification::LIKE, $actor->id, $actor->name, (string) $post->uid))
            ->handle(app(OneSignalPushService::class));
        $this->assertDatabaseHas('likes', ['post_id' => $post->id, 'user_id' => $actor->id]);
    }

    public function test_onesignal_disabled_and_no_subscriber_outcomes_are_safe_noops(): void
    {
        $recipient = User::factory()->create(['locale' => 'en']);
        $pushes = app(OneSignalPushService::class);
        Http::fake();

        config(['services.onesignal.enabled' => false]);
        $this->assertSame(
            OneSignalPushOutcome::ConfigurationMissing,
            $pushes->sendToUsers([$recipient], ['en' => 'Heading'], ['en' => 'Copy'], 'https://skuysocial.test/'),
        );
        Http::assertNothingSent();

        config([
            'services.onesignal.enabled' => true,
            'services.onesignal.app_id' => '11111111-1111-4111-8111-111111111111',
            'services.onesignal.rest_api_key' => 'synthetic-g2-test-key',
        ]);
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['errors' => ['no_subscribers']], 200)]);
        $this->assertSame(
            OneSignalPushOutcome::NoSubscribers,
            $pushes->sendToUsers([$recipient], ['en' => 'Heading'], ['en' => 'Copy'], 'https://skuysocial.test/'),
        );
    }

    public function test_job_suppresses_email_user_generated_name_and_never_sends_external_or_client_urls(): void
    {
        Queue::fake();
        $actor = User::factory()->create(['name' => 'private@example.test']);
        $recipient = User::factory()->create(['locale' => 'en']);
        $recipient->forceFill(['locale' => 'en'])->save();
        config(['app.locale' => 'en']);
        $post = Post::create(['user_id' => $recipient->id, 'content' => 'PRIVATE POST BODY']);
        $this->configureSuccessfulOneSignal();
        config(['app.locale' => 'en']);
        Http::fake(['https://api.onesignal.com/notifications' => Http::response(['id' => 'synthetic-push-id'], 200)]);

        (new SendSocialPushNotification(
            recipientId: $recipient->id,
            event: SendSocialPushNotification::LIKE,
            actorId: $actor->id,
            actorName: $actor->name,
            postUid: (string) $post->uid,
        ))->handle(app(OneSignalPushService::class));

        $recorded = Http::recorded()->last();
        $this->assertNotNull($recorded, 'No HTTP request recorded for recipient-locale push.');
        $payload = $recorded[0]->data();
        $this->assertStringNotContainsString('private@example.test', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('PRIVATE POST BODY', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertNotEmpty($payload['include_aliases']['external_id'] ?? []);
        $this->assertSame('Someone liked your thread.', $payload['contents']['en'] ?? null);
        $this->assertSame('https://skuysocial.test'.route('posts.show', $post->uid, false), $payload['url'] ?? null);
    }

    private function configureSuccessfulOneSignal(): void
    {
        config(['services.onesignal' => [
            'enabled' => true,
            'app_id' => '11111111-1111-4111-8111-111111111111',
            'rest_api_key' => 'synthetic-g2-test-key',
        ]]);
    }
}
