<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowSupportingPagesPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_history_uses_flow_with_real_wallet_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('points.history'))
            ->assertOk()
            ->assertSee('data-flow-page="feed"', false)
            ->assertSee('data-flow-support="points"', false);
    }

    public function test_follower_and_following_lists_use_flow(): void
    {
        $user = User::factory()->create();

        foreach (['user.followers', 'user.following'] as $route) {
            $this->actingAs($user)->get(route($route, $user))
                ->assertOk()
                ->assertSee('data-flow-page="feed"', false)
                ->assertSee('data-flow-support="follows"', false);
        }
    }
}
