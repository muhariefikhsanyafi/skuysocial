<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowTwoFactorPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_settings_uses_the_profile_flow_without_changing_protected_actions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.two-factor.show'));

        $response->assertOk()
            ->assertSee('data-flow-page="profile"', false)
            ->assertSee('data-flow-security="two-factor"', false)
            ->assertSee('action="'.route('security.two-factor.setup').'"', false);
    }
}
