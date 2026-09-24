<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProfilePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_profile_orders_identity_actions_stats_and_tabs_with_existing_routes(): void
    {
        $owner = User::factory()->create([
            'name' => 'Profile Owner with a Long Display Name',
            'username' => 'profile.owner',
            'bio' => 'A thoughtful profile bio.',
            'avatar' => 'avatars/profile-owner.jpg',
            'cover' => 'covers/profile-owner.jpg',
        ]);
        $owner->forceFill(['is_verified' => true])->save();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'A profile thread for presentation coverage.',
        ]);

        $response = $this->actingAs($owner)->get(route('user.profile', $owner));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertViewIs('profile')
            ->assertSee('data-profile-cover="image"', false)
            ->assertSee('covers/profile-owner.jpg', false)
            ->assertSee('avatars/profile-owner.jpg', false)
            ->assertSee('aria-label="'.__('threads.verified').'"', false)
            ->assertSee('Profile Owner with a Long Display Name')
            ->assertSee('@profile.owner')
            ->assertSee('A thoughtful profile bio.')
            ->assertSee('href="'.route('user.edit').'"', false)
            ->assertSee('href="'.route('user.followers', $owner).'"', false)
            ->assertSee('href="'.route('user.following', $owner).'"', false)
            ->assertSee(route('posts.show', $post), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="'.__('profile.threads').', '.__('profile.likes').'"', false);

        $markers = [
            'class="skuy-profile-cover',
            'class="skuy-profile-avatar',
            'class="skuy-profile-identity"',
            'class="skuy-profile-username"',
            'class="skuy-profile-bio',
            'class="skuy-profile-action"',
            'class="skuy-profile-stats"',
            'class="skuy-profile-tabs"',
        ];
        $positions = array_map(static fn (string $marker): int|false => strpos($html, $marker), $markers);

        $this->assertNotContains(false, $positions);
        $sortedPositions = $positions;
        sort($sortedPositions);
        $this->assertSame($sortedPositions, $positions);
        $this->assertSame('user/{username}', Route::getRoutes()->getByName('user.profile')?->uri());
    }

    public function test_other_verified_profile_preserves_follow_state_and_tab_navigation(): void
    {
        $profile = User::factory()->create([
            'name' => 'Verified Profile',
            'username' => 'verified.profile',
            'bio' => 'Public bio',
        ]);
        $profile->forceFill(['is_verified' => true])->save();
        $viewer = User::factory()->create();
        DB::table('follows')->insert([
            'follower_id' => $viewer->id,
            'following_id' => $profile->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get(route('user.profile', $profile).'?tab=likes');

        $response
            ->assertOk()
            ->assertSee('aria-pressed="true"', false)
            ->assertSee(__('profile.following'))
            ->assertSee(route('user.follow', $profile), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('tab=likes', false)
            ->assertSee('Verified Profile')
            ->assertSee('@verified.profile');
    }

    public function test_other_unfollowed_profile_keeps_the_existing_follow_action(): void
    {
        $profile = User::factory()->create(['username' => 'new.follow.target']);
        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('user.profile', $profile));

        $response
            ->assertOk()
            ->assertSee('<form action="'.route('user.follow', $profile).'" method="POST">', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee(__('profile.follow'));
    }

    public function test_unverified_profile_uses_compact_no_cover_fallback_and_existing_empty_bio_copy(): void
    {
        $owner = User::factory()->create([
            'name' => 'No Cover',
            'username' => 'no.cover',
            'bio' => null,
            'cover' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('user.profile', $owner));

        $response
            ->assertOk()
            ->assertSee('data-profile-cover="fallback"', false)
            ->assertSee('skuy-profile-cover--empty', false)
            ->assertSee('skuy-profile-cover__placeholder', false)
            ->assertSee(__('profile.no_bio'))
            ->assertSee('href="'.route('user.edit').'"', false)
            ->assertDontSee('class="skuy-profile-follow-button', false)
            ->assertDontSee('class="skuy-profile-verified"', false);
    }

    public function test_likes_empty_state_uses_localized_status_copy_and_keeps_likes_selected(): void
    {
        $owner = User::factory()->create();
        $likesUrl = route('user.profile', ['username' => $owner->username, 'tab' => 'likes']);

        $response = $this->actingAs($owner)->get($likesUrl);

        $response
            ->assertOk()
            ->assertSee('class="skuy-profile-empty" role="status"', false)
            ->assertSee(__('profile.empty.liked_threads_title'))
            ->assertSee(__('profile.empty.liked_threads_description'))
            ->assertSee($likesUrl, false)
            ->assertSee('class="skuy-profile-tab is-active"', false)
            ->assertDontSee('class="skuy-profile-post', false);
    }

    public function test_profile_multi_image_threads_keep_existing_media_and_feed_presentation_hooks(): void
    {
        $owner = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Profile carousel post',
            'images' => ['media/first.jpg', 'media/second.jpg'],
        ]);

        $response = $this->actingAs($owner)->get(route('user.profile', $owner));

        $response
            ->assertOk()
            ->assertSee('skuy-profile-post', false)
            ->assertSee('data-media-slider="true"', false)
            ->assertSee('data-media-slide="true"', false)
            ->assertSee('media/first.jpg', false)
            ->assertSee('media/second.jpg', false)
            ->assertSee(route('posts.show', $post), false);
    }
}
