<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_feed_uses_functional_navigation_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('feed'));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('href="'.route('user.profile', $user->username).'"', false);

        $this->assertSame(2, substr_count($html, 'href="'.route('search').'"'));
        $this->assertSame(2, substr_count($html, 'href="'.route('activity.index').'"'));
    }

    public function test_authenticated_topic_uses_functional_navigation_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('topik.show', 'navigation-topic'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('search').'"', false)
            ->assertSee('href="'.route('activity.index').'"', false)
            ->assertSee(route('feed'), false);
    }
}
