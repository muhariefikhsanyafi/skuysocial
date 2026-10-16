<?php

namespace Tests\Feature;

use App\Actions\FindOrCreateDirectConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class VerifiedBadgeConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_badge_has_an_accessible_label_and_uses_one_svg_asset(): void
    {
        $markup = Blade::render('<x-verified-badge />');

        $this->assertStringContainsString('data-verified-badge', $markup);
        $this->assertStringContainsString('class="skuy-verified-badge"', $markup);
        $this->assertStringContainsString('src="'.asset('images/verified-badge.svg').'"', $markup);
        $this->assertStringContainsString('aria-label="'.e(__('threads.verified')).'"', $markup);
        $this->assertStringContainsString('alt=""', $markup);
    }

    public function test_verified_profiles_and_messages_reuse_the_shared_badge(): void
    {
        $viewer = User::factory()->create();
        $account = User::factory()->create();
        $account->forceFill(['is_verified' => true])->save();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $account);

        foreach ([
            route('user.profile', $account),
            route('messages.index'),
            route('messages.show', $conversation),
        ] as $url) {
            $this->actingAs($viewer)
                ->get($url)
                ->assertOk()
                ->assertSee('data-verified-badge', false)
                ->assertSee('images/verified-badge.svg', false);
        }
    }

    public function test_unverified_profile_does_not_gain_a_verification_badge(): void
    {
        $viewer = User::factory()->create();
        $account = User::factory()->create(['is_verified' => false]);

        $this->actingAs($viewer)
            ->get(route('user.profile', $account))
            ->assertOk()
            ->assertDontSee('data-verified-badge', false);
    }
}
