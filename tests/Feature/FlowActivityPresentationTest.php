<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowActivityPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_uses_flow_shell_without_changing_the_notification_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('activity.index'))
            ->assertOk()
            ->assertSee('data-flow-page="activity"', false)
            ->assertSee('data-flow-activity="notifications"', false);
    }

    public function test_announcements_index_uses_the_same_flow_shell(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('data-flow-page="activity"', false)
            ->assertSee('data-flow-activity="announcements"', false);
    }
}
