<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileStreamTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_keeps_reference_tabs_and_makes_likes_reachable(): void
    {
        $profile = User::factory()->create(['username' => 'profile-media-tabs']);
        $viewer = User::factory()->create();
        $textPost = $profile->posts()->create(['content' => 'Text-only stream entry']);
        $mediaPost = $profile->posts()->create([
            'content' => 'Media stream entry',
            'images' => ['posts/fixture-image.jpg'],
        ]);

        $response = $this->actingAs($viewer)->get(route('user.profile', [
            'username' => $profile->username,
            'tab' => 'media',
        ]));

        $response->assertOk()
            ->assertViewHas('activeTab', 'media')
            ->assertSee('data-profile-tab="threads"', false)
            ->assertSee('data-profile-tab="replies"', false)
            ->assertSee('data-profile-tab="media"', false)
            ->assertSee('data-profile-tab="reposts"', false)
            ->assertDontSee('data-profile-tab="likes"', false)
            ->assertSee('data-account-menu-key="likes"', false)
            ->assertSee(route('user.profile', ['username' => $viewer->username, 'tab' => 'likes']), false)
            ->assertSee(__('profile.tabs.likes'))
            ->assertSee($mediaPost->content)
            ->assertDontSee($textPost->content);
    }

    public function test_replies_tab_lists_comments_authored_by_the_profile_user(): void
    {
        $profile = User::factory()->create(['username' => 'profile-replies-tabs']);
        $viewer = User::factory()->create();
        $original = $viewer->posts()->create(['content' => 'Original thread']);
        Comment::query()->create([
            'post_id' => $original->id,
            'user_id' => $profile->id,
            'content' => 'A reply written by this profile',
        ]);

        $response = $this->actingAs($viewer)->get(route('user.profile', [
            'username' => $profile->username,
            'tab' => 'replies',
        ]));

        $response->assertOk()
            ->assertViewHas('activeTab', 'replies')
            ->assertSee('A reply written by this profile')
            ->assertSee('Original thread');
    }

    public function test_reposts_tab_only_lists_reposts_and_threads_tab_places_the_pinned_post_first(): void
    {
        $profile = User::factory()->create(['username' => 'profile-reposts-tabs']);
        $viewer = User::factory()->create();
        $oldThread = $profile->posts()->create(['content' => 'Pinned original thread']);
        $newerThread = $profile->posts()->create(['content' => 'Newer original thread']);
        DB::table('posts')->where('id', $oldThread->id)->update(['pinned_at' => now()]);
        $original = $viewer->posts()->create(['content' => 'Original post reposted here']);
        $repost = $profile->posts()->create([
            'content' => 'Repost wrapper',
            'repost_id' => $original->id,
        ]);

        $threadsResponse = $this->actingAs($viewer)->get(route('user.profile', $profile->username));
        $threadsResponse->assertOk()
            ->assertSee('Pinned original thread')
            ->assertSee('Newer original thread');
        $html = (string) $threadsResponse->getContent();
        $this->assertLessThan(strpos($html, 'Newer original thread'), strpos($html, 'Pinned original thread'));

        $repostsResponse = $this->actingAs($viewer)->get(route('user.profile', [
            'username' => $profile->username,
            'tab' => 'reposts',
        ]));
        $repostsResponse->assertOk()
            ->assertViewHas('activeTab', 'reposts')
            ->assertSee('profile-reposts-tabs')
            ->assertSee($original->content)
            ->assertDontSee($repost->content)
            ->assertDontSee('Pinned original thread')
            ->assertDontSee('Newer original thread');
    }
}
