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

    public function test_profile_uses_flow_verification_while_messages_keep_the_existing_shared_badge(): void
    {
        $viewer = User::factory()->create();
        $account = User::factory()->create();
        $account->forceFill(['is_verified' => true])->save();
        $conversation = app(FindOrCreateDirectConversation::class)->handle($viewer, $account);

        $this->actingAs($viewer)
            ->get(route('user.profile', $account))
            ->assertOk()
            ->assertSee('data-verified-badge', false)
            ->assertSee('data-flow-verified', false);

        // Other surfaces retain their existing SVG asset until explicitly redesigned.
        foreach ([route('messages.index'), route('messages.show', $conversation)] as $url) {
            $this->actingAs($viewer)
                ->get($url)
                ->assertOk()
                ->assertSee('data-verified-badge', false)
                ->assertSee('images/verified-badge.svg', false);
        }
    }

    public function test_flow_badge_variant_renders_accessible_inline_svg(): void
    {
        $markup = Blade::render('<x-verified-badge flow />');

        $this->assertStringContainsString('data-flow-verified', $markup);
        $this->assertStringContainsString('<svg', $markup);
        $this->assertStringContainsString('aria-label="'.e(__('threads.verified')).'"', $markup);
        $this->assertStringNotContainsString('images/verified-badge.svg', $markup);
    }

    public function test_profile_post_cards_use_the_same_violet_flow_badge_for_authenticated_and_guest_views(): void
    {
        $owner = User::factory()->create(['is_verified' => true]);
        \App\Models\Post::query()->create([
            'user_id' => $owner->id,
            'content' => 'Verified profile thread',
        ]);

        $viewer = User::factory()->create();
        $url = route('user.profile', $owner);

        $authenticated = $this->actingAs($viewer)->get($url);
        auth()->logout();
        $guest = $this->get($url);

        foreach ([$authenticated, $guest] as $response) {
            $response->assertOk()
                ->assertSee('Verified profile thread')
                ->assertDontSee('images/verified-badge.svg', false);

            $this->assertGreaterThanOrEqual(
                2,
                substr_count($response->getContent(), 'data-flow-verified'),
                'Both the profile name and its verified post card must use Flow badges.',
            );
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
