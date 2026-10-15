<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowDiscoveryPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_page_uses_the_flow_discovery_surface(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('topik.show', ['topic' => 'design']))
            ->assertOk()
            ->assertSee('data-flow-page="feed"', false)
            ->assertSee('data-flow-discovery="topic"', false);
    }

    public function test_hashtag_page_uses_the_flow_discovery_surface(): void
    {
        $user = User::factory()->create();
        Hashtag::query()->create(['name' => 'skuyflow']);

        $this->actingAs($user)->get(route('tags.show', ['name' => 'skuyflow']))
            ->assertOk()
            ->assertSee('data-flow-page="feed"', false)
            ->assertSee('data-flow-discovery="hashtag"', false);
    }
}
