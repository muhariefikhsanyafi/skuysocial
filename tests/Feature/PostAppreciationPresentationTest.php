<?php

namespace Tests\Feature;

use App\Actions\ApplyPointTransaction;
use App\Enums\PointTransactionType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PostAppreciationPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_places_the_star_trigger_and_three_choice_form_in_the_primary_action_row(): void
    {
        $viewer = User::factory()->create(['locale' => 'id']);
        $owner = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $owner->getKey(),
            'content' => 'Inline Appreciation target.',
        ]);
        $this->credit($viewer, 10);
        $response = $this->actingAs($viewer)->get(route('feed'));
        $response->assertOk();

        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $actionRow = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " skuy-actions--primary ")]')->item(0);

        $this->assertNotNull($actionRow);
        $trigger = $xpath->query('.//button[@data-post-appreciation-trigger]', $actionRow)->item(0);
        $this->assertNotNull($trigger);
        $this->assertInstanceOf(\DOMElement::class, $trigger);
        $this->assertSame('button', $trigger->getAttribute('type'));
        $this->assertFalse($trigger->hasAttribute('href'));
        $this->assertNotNull($xpath->query('.//i[contains(concat(" ", normalize-space(@class), " "), " fa-star ")]', $actionRow)->item(0));
        $this->assertNotNull($xpath->query('.//form[@action="'.route('posts.appreciations.store', $post).'"]//input[@name="surface" and @value="feed"]', $actionRow)->item(0));
        $this->assertSame(1, $xpath->query('//*[@data-post-appreciation]')->length);
        $this->assertSame(0, $xpath->query('//summary[contains(concat(" ", normalize-space(@class), " "), " skuy-appreciation__trigger ")]')->length);
        $mobileActions = [];
        foreach ($xpath->query('.//*[@data-mobile-primary-action]', $actionRow) as $mobileAction) {
            if ($mobileAction instanceof \DOMElement) {
                $mobileActions[] = $mobileAction->getAttribute('data-mobile-primary-action');
            }
        }
        $this->assertSame(['like', 'appreciation', 'comment', 'repost', 'share'], $mobileActions);
        $this->assertSame(6, $xpath->query('./*[self::form or self::button or self::section]', $actionRow)->length);
        $this->assertNotNull($xpath->query('.//form[contains(@class, "skuy-post-actions__desktop-bookmark")]', $actionRow)->item(0));
        $this->assertNotNull($xpath->query('//*[@data-mobile-post-options="'.$post->uid.'"]')->item(0));
        $choiceValues = [];
        foreach ($xpath->query('.//button[@type="submit" and @name="type"]', $actionRow) as $choiceButton) {
            if ($choiceButton instanceof \DOMElement) {
                $choiceValues[] = $choiceButton->getAttribute('value');
            }
        }
        $this->assertSame(['bermanfaat', 'inspiratif', 'solutif'], $choiceValues);
        $response->assertSee(__('appreciation.cost_copy', [
            'cost' => 5,
            'unit' => __('points.unit'),
        ]));
    }

    public function test_feed_appreciation_chooser_has_a_mobile_sheet_and_explicit_dismiss_control(): void
    {
        $viewer = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Mobile Appreciation sheet target.',
        ]);
        $this->credit($viewer, 5);

        $response = $this->actingAs($viewer)->get(route('feed'));
        $response->assertOk();

        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $appreciation = $xpath->query('//*[@data-post-appreciation="'.$post->uid.'"]')->item(0);

        $this->assertNotNull($appreciation);
        $this->assertNotNull($xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " skuy-appreciation__overlay ")]', $appreciation)->item(0));
        $dialog = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " skuy-appreciation__popover ") and @role="dialog"]', $appreciation)->item(0);
        $this->assertNotNull($dialog);
        $this->assertNotNull($xpath->query('.//button[contains(concat(" ", normalize-space(@class), " "), " skuy-appreciation__dismiss ")]', $dialog)->item(0));
        $this->assertNotNull($xpath->query('.//div[@role="group"]//button[@type="submit" and @name="type"]', $dialog)->item(0));
    }

    public function test_feed_submission_returns_to_feed_without_a_referer(): void
    {
        $viewer = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Feed redirect target.',
        ]);
        $this->credit($viewer, 5);

        $this->actingAs($viewer)
            ->post(route('posts.appreciations.store', $post), ['type' => 'bermanfaat', 'surface' => 'feed'])
            ->assertRedirect(route('feed'))
            ->assertSessionHas('post_appreciation_post_uid', (string) $post->uid)
            ->assertSessionHas('post_appreciation_feedback_error', false)
            ->assertSessionHas('post_appreciation_motion_post_uid', (string) $post->uid);

        $this->get(route('feed'))
            ->assertOk()
            ->assertSee(__('appreciation.feedback.success', [
                'type' => __('appreciation.types.bermanfaat'),
                'cost' => 5,
                'unit' => __('points.unit'),
            ]))
            ->assertSee('skuy-social-motion--appreciation', false);

        $this->get(route('feed'))
            ->assertOk()
            ->assertDontSee('skuy-social-motion--appreciation', false);
    }

    public function test_feed_validation_error_returns_to_feed_and_is_rendered_there(): void
    {
        $viewer = User::factory()->create(['locale' => 'id']);
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Feed validation target.',
        ]);

        $this->actingAs($viewer)
            ->post(route('posts.appreciations.store', $post), ['type' => 'invalid', 'surface' => 'feed'])
            ->assertRedirect(route('feed'))
            ->assertSessionHasErrors(['type'])
            ->assertSessionHas('post_appreciation_post_uid', (string) $post->uid)
            ->assertSessionMissing('post_appreciation_motion_post_uid');

        $this->get(route('feed'))
            ->assertOk()
            ->assertSee(__('appreciation.validation.type_invalid'));
    }

    public function test_post_detail_validation_error_returns_to_the_post_detail(): void
    {
        config(['frontend.post_detail_inertia' => false]);

        $viewer = User::factory()->create(['locale' => 'en']);
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Detail validation target.',
        ]);

        $this->actingAs($viewer)
            ->post(route('posts.appreciations.store', $post), ['type' => 'invalid', 'surface' => 'detail'])
            ->assertRedirect(route('posts.show', $post))
            ->assertSessionHasErrors(['type'])
            ->assertSessionHas('post_appreciation_post_uid', (string) $post->uid);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee(__('appreciation.validation.type_invalid'));
    }

    public function test_post_detail_submission_returns_to_the_post_detail_without_a_referer(): void
    {
        $viewer = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Detail redirect target.',
        ]);
        $this->credit($viewer, 5);

        $this->actingAs($viewer)
            ->post(route('posts.appreciations.store', $post), ['type' => 'bermanfaat', 'surface' => 'detail'])
            ->assertRedirect(route('posts.show', $post));
    }

    public function test_feed_denial_feedback_returns_to_feed_for_insufficient_points_and_self_appreciation(): void
    {
        $viewer = User::factory()->create(['locale' => 'id']);
        $owner = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $owner->getKey(),
            'content' => 'Insufficient points feed target.',
        ]);
        $insufficientPointsMessage = __('appreciation.feedback.insufficient_points', [
            'cost' => 5,
            'unit' => __('points.unit'),
        ]);

        $this->actingAs($viewer)
            ->post(route('posts.appreciations.store', $post), ['type' => 'bermanfaat', 'surface' => 'feed'])
            ->assertRedirect(route('feed'))
            ->assertSessionHas('post_appreciation_feedback', $insufficientPointsMessage)
            ->assertSessionHas('post_appreciation_feedback_error', true);

        $this->get(route('feed'))
            ->assertOk()
            ->assertSee($insufficientPointsMessage);

        $self = User::factory()->create(['locale' => 'id']);
        $selfPost = Post::query()->create([
            'user_id' => $self->getKey(),
            'content' => 'Self appreciation feed target.',
        ]);
        $this->credit($self, 5);
        $selfAppreciationMessage = __('appreciation.feedback.self_appreciation_forbidden');

        $this->actingAs($self)
            ->post(route('posts.appreciations.store', $selfPost), ['type' => 'solutif', 'surface' => 'feed'])
            ->assertRedirect(route('feed'))
            ->assertSessionHas('post_appreciation_feedback', $selfAppreciationMessage)
            ->assertSessionHas('post_appreciation_feedback_error', true);

        $this->get(route('feed'))
            ->assertOk()
            ->assertSee($selfAppreciationMessage);
    }

    public function test_feed_exposes_the_localized_three_choice_control_and_fixed_cost(): void
    {
        $viewer = User::factory()->create(['locale' => 'id']);
        $owner = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $owner->getKey(),
            'content' => 'Useful post for the feed.',
        ]);
        $this->credit($viewer, 10);

        $response = $this->actingAs($viewer)->get(route('feed'));

        $response
            ->assertOk()
            ->assertSee(__('appreciation.actions.appreciate'))
            ->assertSee(__('appreciation.actions.choose_type'))
            ->assertSee(__('appreciation.types.bermanfaat'))
            ->assertSee(__('appreciation.types.inspiratif'))
            ->assertSee(__('appreciation.types.solutif'))
            ->assertSee(__('appreciation.cost_copy', [
                'cost' => 5,
                'unit' => __('points.unit'),
            ]))
            ->assertSee(route('posts.appreciations.store', $post), false);
    }

    public function test_feed_shows_aggregate_and_selected_type_without_another_submission_control(): void
    {
        $viewer = User::factory()->create(['locale' => 'id']);
        $owner = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => $owner->getKey(),
            'content' => 'A post already appreciated by the viewer.',
        ]);
        $this->credit($viewer, 5);

        $this->actingAs($viewer)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'inspiratif'])
            ->assertRedirect();

        $response = $this->get(route('feed'));

        $response
            ->assertOk()
            ->assertSee(trans_choice('appreciation.count', 1, ['count' => 1]))
            ->assertSee(__('appreciation.selected', ['type' => __('appreciation.types.inspiratif')]))
            ->assertDontSee('Pilih jenis apresiasi')
            ->assertDontSee(route('posts.appreciations.store', $post), false);
    }

    public function test_feed_does_not_offer_appreciation_on_the_viewers_own_post(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        $post = Post::query()->create([
            'user_id' => $owner->getKey(),
            'content' => 'The viewer owns this post.',
        ]);

        $response = $this->actingAs($owner)->get(route('feed'));

        $response
            ->assertOk()
            ->assertSee('The viewer owns this post.')
            ->assertDontSee(route('posts.appreciations.store', $post), false)
            ->assertDontSee('data-post-appreciation="'.$post->uid.'"', false);
    }

    public function test_blade_post_detail_renders_selected_appreciation_state(): void
    {
        config(['frontend.post_detail_inertia' => false]);

        $viewer = User::factory()->create(['locale' => 'en']);
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Legacy detail appreciation target.',
        ]);
        $this->credit($viewer, 5);
        $this->actingAs($viewer)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'solutif'])
            ->assertRedirect();

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee(trans_choice('appreciation.count', 1, ['count' => 1]))
            ->assertSee(__('appreciation.selected', ['type' => __('appreciation.types.solutif')]))
            ->assertDontSee(route('posts.appreciations.store', $post), false);
    }

    public function test_inertia_post_detail_exposes_only_aggregate_and_viewer_appreciation_state(): void
    {
        config(['frontend.post_detail_inertia' => true]);

        $viewer = User::factory()->create(['locale' => 'id']);
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Inertia detail appreciation target.',
        ]);
        $this->credit($viewer, 5);
        $this->actingAs($viewer)
            ->post('/posts/'.$post->uid.'/appreciation', ['type' => 'bermanfaat'])
            ->assertRedirect();

        $response = $this->actingAs($viewer)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/')) ?? '',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get(route('posts.show', $post));

        $response
            ->assertOk()
            ->assertJsonPath('props.display_post.interactions.appreciation_count', 1)
            ->assertJsonPath('props.display_post.interactions.appreciated', true)
            ->assertJsonPath('props.display_post.interactions.appreciation_type', 'bermanfaat')
            ->assertJsonPath('props.routes.appreciation', route('posts.appreciations.store', $post))
            ->assertJsonPath('props.translations.appreciation.types.bermanfaat', 'Bermanfaat');

        $this->assertArrayNotHasKey('appreciations', $response->json('props.display_post'));
    }

    public function test_blade_post_detail_keeps_mobile_bookmark_outside_its_five_primary_actions(): void
    {
        config(['frontend.post_detail_inertia' => false]);

        $viewer = User::factory()->create();
        $post = Post::query()->create([
            'user_id' => User::factory()->create()->getKey(),
            'content' => 'Mobile actions detail target.',
        ]);

        $response = $this->actingAs($viewer)->get(route('posts.show', $post));
        $response->assertOk();

        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NONET);
        $xpath = new \DOMXPath($document);
        $actionRow = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " skuy-actions--primary ")]')->item(0);

        $this->assertNotNull($actionRow);
        $mobileActions = [];
        foreach ($xpath->query('.//*[@data-mobile-primary-action]', $actionRow) as $mobileAction) {
            if ($mobileAction instanceof \DOMElement) {
                $mobileActions[] = $mobileAction->getAttribute('data-mobile-primary-action');
            }
        }

        $this->assertSame(['like', 'appreciation', 'comment', 'repost', 'share'], $mobileActions);
        $this->assertNotNull($xpath->query('.//form[contains(@class, "skuy-post-actions__desktop-bookmark")]', $actionRow)->item(0));
        $this->assertNotNull($xpath->query('//*[@data-mobile-post-options="'.$post->uid.'"]')->item(0));
    }

    private function credit(User $user, int $amount): void
    {
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            $amount,
            'test_credit',
            'test',
            'post-appreciation-presentation-'.$user->getKey(),
            'r6c:presentation:'.$user->getKey(),
        );
    }
}
