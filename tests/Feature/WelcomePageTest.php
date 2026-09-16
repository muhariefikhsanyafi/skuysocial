<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_welcome_has_semantic_structure_and_real_auth_destinations(): void
    {
        $response = $this->withSession(['locale' => 'id'])->get('/');
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('<header class="skuy-guest-header">', false)
            ->assertSee('<main id="main-content" class="skuy-welcome">', false)
            ->assertSee('<footer class="skuy-guest-footer">', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('action="'.route('locale.update').'"', false)
            ->assertSee('aria-label="Pilih bahasa"', false)
            ->assertSee('aria-label="Aktifkan mode gelap"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee(':aria-pressed="darkMode"', false)
            ->assertSee("window.localStorage.getItem('theme')", false)
            ->assertSee("prefers-color-scheme: dark", false)
            ->assertSee("document.documentElement.classList.toggle('dark', darkMode)", false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('href=""', false);

        $this->assertSame(1, substr_count($html, '<h1 '));
    }

    public function test_authenticated_welcome_points_every_primary_entry_to_the_feed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('href="'.url('/feed').'"', false)
            ->assertSeeText('Buka beranda');

        $this->assertStringNotContainsString('href="'.route('register').'"', $html);
        $this->assertStringNotContainsString('href="'.route('login').'"', $html);
    }

    public function test_welcome_copy_and_root_language_follow_the_guest_locale(): void
    {
        foreach ([
            'id' => [
                'hero' => 'Mulai ngobrol dengan caramu sendiri.',
                'description' => 'Buat utas, temukan topik yang ingin kamu ikuti, dan ikut percakapan dengan caramu sendiri.',
                'value' => 'Pilih cara ikut.',
                'closing' => 'Punya sesuatu untuk dibicarakan?',
            ],
            'en' => [
                'hero' => 'Start a conversation in your own voice.',
                'description' => 'Start a thread, find topics you want to follow, and join conversations in your own way.',
                'value' => 'Choose how you join in.',
                'closing' => 'Have something to say?',
            ],
        ] as $locale => $copy) {
            $response = $this->withSession(['locale' => $locale])->get('/');

            $response
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'">', false)
                ->assertSeeText($copy['hero'])
                ->assertSeeText($copy['description'])
                ->assertSeeText($copy['value'])
                ->assertSeeText($copy['closing']);
        }
    }
}
