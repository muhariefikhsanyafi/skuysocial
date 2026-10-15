<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class ThemeInitializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_documents_initialize_theme_before_stylesheets(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::query()->create(['user_id' => $other->id, 'content' => 'Synthetic theme test']);
        $routes = [
            route('feed'), route('user.profile', $user->username), route('user.profile', $other->username),
            route('user.edit', $user->username), route('profile.edit', $user->username),
            route('bookmarks.index'), route('search'), route('activity.index'), route('messages.index'),
            route('points.history'), route('dashboard'), route('posts.show', $post), '/', '/missing-theme-test',
        ];

        foreach ($routes as $route) {
            $html = $this->actingAs($user)->get($route)->getContent();
            $this->assertBootstrapPrecedesStyles($html, $route);
        }

        auth()->forgetGuards();
        $this->assertBootstrapPrecedesStyles($this->get(route('login'))->getContent(), 'login');
    }

    public function test_theme_bootstrap_reuses_the_vite_csp_nonce(): void
    {
        Vite::useCspNonce('synthetic-theme-test-nonce');
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<script\s+data-skuy-theme\s+nonce="synthetic-theme-test-nonce"/', $html);
        $this->assertBootstrapPrecedesStyles($html, 'nonce document');
    }

    private function assertBootstrapPrecedesStyles(string $html, string $route): void
    {
        $bootstrap = strpos($html, 'data-skuy-theme');
        $stylesheet = strpos($html, 'rel="stylesheet"');
        $this->assertNotFalse($bootstrap, $route);
        $this->assertNotFalse($stylesheet, $route);
        $this->assertLessThan($stylesheet, $bootstrap, $route);
        $this->assertSame(1, substr_count($html, 'data-skuy-theme'), $route);
    }
}
