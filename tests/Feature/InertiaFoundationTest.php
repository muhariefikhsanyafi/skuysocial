<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\TestCase;

class InertiaFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'inertia'])->get('/__inertia-foundation', static fn () => Inertia::render(
            'Foundation/Probe',
            ['message' => 'foundation-ready'],
        ));
    }

    public function test_initial_inertia_visit_renders_the_dedicated_root_template(): void
    {
        $response = $this
            ->withSession(['locale' => 'en'])
            ->get('/__inertia-foundation');

        $response
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('id="app"', false)
            ->assertSee('data-page=', false)
            ->assertSee('Foundation', false)
            ->assertSee('Probe', false)
            ->assertSee('foundation-ready', false)
            ->assertSee('name="csrf-token"', false);
    }

    public function test_inertia_request_exposes_minimal_shared_props_without_private_user_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Foundation User',
            'username' => 'foundation_user',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'locale' => 'en',
                'status' => 'profile-updated',
            ])
            ->withHeaders($this->inertiaHeaders())
            ->get('/__inertia-foundation');

        $response
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertHeader('Vary', 'X-Inertia')
            ->assertJsonPath('component', 'Foundation/Probe')
            ->assertJsonPath('props.message', 'foundation-ready')
            ->assertJsonPath('props.locale', 'en')
            ->assertJsonPath('props.flash.status', 'profile-updated')
            ->assertJsonPath('props.auth.user.id', $user->id)
            ->assertJsonPath('props.auth.user.name', 'Foundation User')
            ->assertJsonPath('props.auth.user.username', 'foundation_user')
            ->assertJsonPath('props.moderation.state', 'active')
            ->assertJsonPath('props.moderation.allows_social_writes', true);

        $sharedUser = $response->json('props.auth.user');

        $this->assertSame(
            ['id', 'name', 'username'],
            array_keys($sharedUser),
        );
        $this->assertArrayNotHasKey('email', $sharedUser);
    }

    public function test_non_inertia_authenticated_blade_request_remains_normal_html(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertHeaderMissing('X-Inertia')
            ->assertSee('class="skuy-app-shell"', false)
            ->assertDontSee('data-page=', false);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
