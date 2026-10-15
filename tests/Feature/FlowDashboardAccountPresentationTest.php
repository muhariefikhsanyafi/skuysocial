<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowDashboardAccountPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_flow_navigation_without_changing_daily_checkin_action(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-flow-page="feed"', false)
            ->assertSee('data-flow-dashboard="home"', false)
            ->assertSee('action="'.route('daily-checkin.store').'"', false)
            ->assertSee('data-mobile-nav-key="messages"', false);
    }

    public function test_account_settings_uses_profile_flow_and_retains_security_and_deletion_forms(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit', $user->username));

        $response->assertOk()
            ->assertSee('data-flow-page="profile"', false)
            ->assertSee('data-flow-account="settings"', false)
            ->assertSee('action="'.route('profile.update').'"', false)
            ->assertSee('action="'.route('profile.destroy').'"', false)
            ->assertSee('href="'.route('security.two-factor.show').'"', false);
    }
}
