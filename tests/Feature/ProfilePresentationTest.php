<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProfilePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_omits_the_empty_shell_notice_without_duplicate_profile_content(): void
    {
        $owner = User::factory()->create();
        $response = $this->actingAs($owner)->get(route('user.profile', $owner));
        $html = $response->getContent();

        $response->assertOk();
        $this->assertStringNotContainsString('class="skuy-shell-notice', $html);

        $positions = [
            strpos($html, '<main id="main-content"'),
            strpos($html, '<div class="skuy-profile-page">'),
            strpos($html, 'class="skuy-profile-back"'),
            strpos($html, '<section class="skuy-profile-header"'),
            strpos($html, 'class="skuy-profile-cover '),
        ];
        $this->assertNotContains(false, $positions);
        $sortedPositions = $positions;
        sort($sortedPositions);
        $this->assertSame($sortedPositions, $positions);
    }

    public function test_settings_pages_omit_the_empty_shell_notice(): void
    {
        $viewer = User::factory()->create();

        foreach ([route('profile.edit', $viewer->username), route('user.edit', $viewer->username)] as $url) {
            $response = $this->actingAs($viewer)->get($url);

            $response->assertOk();
            $this->assertStringNotContainsString('class="skuy-shell-notice', $response->getContent());
        }
    }

    public function test_profile_shell_preserves_the_existing_restricted_notice(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['moderation_state' => ModerationState::Restricted->value])->save();

        $response = $this->actingAs($owner)->get(route('user.profile', $owner));

        $response
            ->assertOk()
            ->assertSee('class="skuy-shell-notice skuy-shell-notice--profile"', false)
            ->assertSee('id="restricted-account-notice-title"', false)
            ->assertSee(__('moderation.restricted.notice_title'))
            ->assertSee(__('moderation.restricted.notice_body'))
            ->assertSee('skuy-profile-cover', false);
    }

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
            ->assertSee('href="'.route('user.edit', $owner->username).'"', false)
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
        $this->assertSame('@{username}', Route::getRoutes()->getByName('user.profile')?->uri());
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
            ->assertSee('href="'.route('user.edit', $owner->username).'"', false)
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

    public function test_profile_and_settings_named_routes_use_the_canonical_username_urls(): void
    {
        $owner = User::factory()->create(['username' => 'canonical.owner']);

        $this->assertSame('/@canonical.owner', route('user.profile', $owner->username, false));
        $this->assertSame('/@canonical.owner/pengaturan-profil', route('user.edit', $owner->username, false));
        $this->assertSame('/@canonical.owner/pengaturan-akun', route('profile.edit', $owner->username, false));
    }

    public function test_canonical_settings_only_resolve_for_the_authenticated_users_username(): void
    {
        $owner = User::factory()->create(['username' => 'canonical.owner']);
        User::factory()->create(['username' => 'other.owner']);

        $this->actingAs($owner)
            ->get('/@canonical.owner/pengaturan-profil')
            ->assertOk()
            ->assertViewIs('edit-profile');

        $this->actingAs($owner)
            ->get('/@canonical.owner/pengaturan-akun')
            ->assertOk()
            ->assertViewIs('profile.edit');

        $this->actingAs($owner)
            ->get('/@other.owner/pengaturan-profil')
            ->assertNotFound();

        $this->actingAs($owner)
            ->get('/@other.owner/pengaturan-akun')
            ->assertNotFound();
    }

    public function test_guests_keep_the_existing_login_flow_for_private_settings_routes(): void
    {
        foreach ([
            '/@canonical.owner/pengaturan-profil',
            '/@canonical.owner/pengaturan-akun',
            '/profile',
            '/pengaturan/profil',
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_legacy_profile_routes_redirect_to_canonical_urls_with_query_strings(): void
    {
        $owner = User::factory()->create(['username' => 'canonical.owner']);

        $this->actingAs($owner)
            ->get('/user/canonical.owner?tab=likes')
            ->assertRedirect(route('user.profile', ['username' => $owner->username, 'tab' => 'likes']));

        $this->actingAs($owner)
            ->get('/profile?section=security')
            ->assertRedirect(route('profile.edit', ['username' => $owner->username, 'section' => 'security']));

        $this->actingAs($owner)
            ->get('/pengaturan/profil?section=details')
            ->assertRedirect(route('user.edit', ['username' => $owner->username, 'section' => 'details']));
    }

    public function test_profile_and_account_settings_show_only_their_responsibility_areas(): void
    {
        $owner = User::factory()->create(['username' => 'settings.owner']);

        $profileSettings = $this->actingAs($owner)
            ->get(route('user.edit', $owner->username))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="name"', $profileSettings);
        $this->assertMatchesRegularExpression('/<input(?=[^>]*id="username")(?=[^>]*disabled)[^>]*>/', $profileSettings);
        $this->assertStringContainsString('name="bio"', $profileSettings);
        $this->assertStringContainsString('name="avatar"', $profileSettings);
        $this->assertStringContainsString('name="cover"', $profileSettings);
        $this->assertStringNotContainsString('name="email"', $profileSettings);
        $this->assertStringNotContainsString('name="current_password"', $profileSettings);
        $this->assertStringNotContainsString(route('security.two-factor.show'), $profileSettings);
        $this->assertStringNotContainsString('name="_method" value="delete"', $profileSettings);

        $accountSettings = $this->actingAs($owner)
            ->get(route('profile.edit', $owner->username))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="email"', $accountSettings);
        $this->assertStringContainsString(route('verification.send'), $accountSettings);
        $this->assertStringContainsString('name="current_password"', $accountSettings);
        $this->assertStringContainsString(route('security.two-factor.show'), $accountSettings);
        $this->assertStringNotContainsString('name="name"', $accountSettings);
        $this->assertStringNotContainsString('name="username"', $accountSettings);
        $this->assertStringNotContainsString('name="bio"', $accountSettings);
        $this->assertStringNotContainsString('name="avatar"', $accountSettings);
        $this->assertStringNotContainsString('name="cover"', $accountSettings);
        $this->assertStringContainsString(__('settings.delete.danger_heading'), $accountSettings);
        $this->assertStringContainsString('name="username_confirmation"', $accountSettings);
        $this->assertStringContainsString('name="_method" value="delete"', $accountSettings);
    }
}
