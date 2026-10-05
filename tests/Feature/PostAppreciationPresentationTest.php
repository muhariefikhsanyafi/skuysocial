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
