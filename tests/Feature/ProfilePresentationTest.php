<?php

namespace Tests\Feature;

use App\Enums\ModerationState;
use App\Enums\ProfileVisibility;
use App\Models\Bookmark;
use App\Models\Like;
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
            strpos($html, '<div class="skuy-profile-page" data-skuy-profile>'),
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

    public function test_profile_tabs_are_a_standalone_region_after_the_hero_card(): void
    {
        $owner = User::factory()->create();
        $html = $this->actingAs($owner)->get(route('user.profile', $owner))->assertOk()->getContent();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $hero = $xpath->query('//*[@data-profile-hero]')->item(0);
        $tabs = $xpath->query('//*[@data-profile-tabs]')->item(0);

        $this->assertNotNull($hero);
        $this->assertNotNull($tabs);
        $this->assertSame(0, $xpath->query('.//*[@data-profile-tabs]', $hero)->length);
        $this->assertSame($hero->parentNode, $tabs->parentNode);
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
            ->assertSee('data-verified-badge', false)
            ->assertSee('Profile Owner with a Long Display Name')
            ->assertSee('@profile.owner')
            ->assertSee('A thoughtful profile bio.')
            ->assertSee('href="'.route('user.edit', $owner->username).'"', false)
            ->assertSee('href="'.route('user.followers', $owner).'"', false)
            ->assertSee('href="'.route('user.following', $owner).'"', false)
            ->assertSee(route('posts.show', $post), false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="'.__('profile.threads').', '.__('profile.followers').', '.__('profile.following').'"', false);

        $this->assertSame(2, substr_count($html, 'data-verified-badge'));

        $markers = [
            'class="skuy-profile-cover',
            'class="skuy-profile-avatar',
            'class="skuy-profile-action"',
            'class="skuy-profile-identity"',
            'class="skuy-profile-username"',
            'class="skuy-profile-bio',
            'data-profile-statistics',
            'class="skuy-profile-tabs"',
        ];
        $positions = array_map(static fn (string $marker): int|false => strpos($html, $marker), $markers);

        $this->assertNotContains(false, $positions);
        $sortedPositions = $positions;
        sort($sortedPositions);
        $this->assertSame($sortedPositions, $positions);
        $this->assertSame('@{username}', Route::getRoutes()->getByName('user.profile')?->uri());
    }

    public function test_prism_profile_uses_hero_hierarchy_owner_settings_and_feed_post_actions(): void
    {
        $owner = User::factory()->create([
            'name' => 'A Long Profile Owner Name',
            'username' => 'prism.profile.owner',
            'bio' => 'A profile bio that remains readable below the hero.',
        ]);
        $owner->forceFill(['is_verified' => true])->save();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Profile feed action contract.',
            'images' => ['media/profile-one.jpg', 'media/profile-two.jpg'],
        ]);

        $html = $this->actingAs($owner)->get(route('user.profile', $owner))->assertOk()->getContent();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $profile = $xpath->query('//*[@data-skuy-profile]')->item(0);

        $this->assertNotNull($profile, 'The profile should expose a stable semantic root.');
        $this->assertSame(1, $xpath->query('.//*[@data-profile-hero]', $profile)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-profile-identity]', $profile)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-profile-statistics]', $profile)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-profile-tabs]', $profile)->length);

        $ownerActions = $xpath->query('.//*[@data-profile-owner-actions]', $profile)->item(0);
        $this->assertNotNull($ownerActions);
        $this->assertSame(1, $xpath->query('.//a[@href="'.route('user.edit', $owner->username).'"]', $ownerActions)->length);
        $this->assertSame(1, $xpath->query('.//a[@href="'.route('profile.edit', $owner->username).'"]', $ownerActions)->length);

        $postCard = $xpath->query('.//article[@data-feed-post-uid="'.$post->uid.'"]', $profile)->item(0);
        $this->assertNotNull($postCard, 'Profile posts should use the shared Feed presentation.');
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="like"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-feed-post-action="bookmark"]', $postCard)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-media-slider="true"]', $postCard)->length);
        $this->assertSame(2, $xpath->query('.//*[@data-verified-badge]', $profile)->length);
    }

    public function test_public_profile_uses_the_same_prism_hierarchy_without_owner_actions(): void
    {
        $profileOwner = User::factory()->create(['username' => 'prism.public.profile']);
        $viewer = User::factory()->create();

        $html = $this->actingAs($viewer)->get(route('user.profile', $profileOwner))->assertOk()->getContent();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $profile = $xpath->query('//*[@data-skuy-profile]')->item(0);

        $this->assertNotNull($profile, 'Public profiles should use the shared profile composition.');
        $this->assertSame(1, $xpath->query('.//*[@data-profile-hero]', $profile)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-profile-owner-actions]', $profile)->length);
        $this->assertSame(1, $xpath->query('.//form[@action="'.route('user.follow', $profileOwner).'"]', $profile)->length);
        $this->assertSame(0, $xpath->query('.//a[@href="'.route('user.edit', $profileOwner->username).'"]', $profile)->length);
        $this->assertSame(0, $xpath->query('.//a[@href="'.route('profile.edit', $profileOwner->username).'"]', $profile)->length);
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

    public function test_profile_reference_tab_row_has_four_tabs_and_keeps_likes_in_account_menus(): void
    {
        $owner = User::factory()->create();
        $likesUrl = route('user.profile', ['username' => $owner->username, 'tab' => 'likes']);

        $html = $this->actingAs($owner)->get(route('user.profile', $owner))->assertOk()->getContent();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $tabValues = [];

        foreach ($xpath->query('//*[@data-profile-tabs]//*[@data-profile-tab]/@data-profile-tab') as $attribute) {
            $tabValues[] = $attribute->nodeValue;
        }

        $this->assertSame(['threads', 'replies', 'media', 'reposts'], $tabValues);
        $this->assertSame(1, $xpath->query('//*[@id="desktop-profile-menu"]//a[@data-account-menu-key="likes" and @href="'.$likesUrl.'"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="mobile-profile-menu"]//a[@data-account-menu-key="likes" and @href="'.$likesUrl.'"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="rail-account-menu"]//a[@data-account-menu-key="likes" and @href="'.$likesUrl.'"]')->length);
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

    public function test_profile_relationship_rail_uses_real_followers_and_followings(): void
    {
        $profileOwner = User::factory()->create(['username' => 'context.profile.owner']);
        $follower = User::factory()->create(['username' => 'context.profile.follower']);
        $following = User::factory()->create(['username' => 'context.profile.following']);
        $viewer = User::factory()->create();

        DB::table('follows')->insert([
            [
                'follower_id' => $follower->id,
                'following_id' => $profileOwner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'follower_id' => $profileOwner->id,
                'following_id' => $following->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($viewer)->get(route('user.profile', $profileOwner));

        $response->assertOk()
            ->assertViewHas('profileFollowers', fn ($users): bool => $users->contains('id', $follower->id))
            ->assertViewHas('profileFollowings', fn ($users): bool => $users->contains('id', $following->id))
            ->assertSee('data-profile-context', false)
            ->assertSee(route('user.profile', $follower), false)
            ->assertSee(route('user.profile', $following), false)
            ->assertSee($follower->name)
            ->assertSee($following->name);
    }

    public function test_profile_relationship_summary_counts_followers_beyond_the_avatar_preview(): void
    {
        $profileOwner = User::factory()->create();
        $followers = User::factory()->count(5)->create();
        $viewer = User::factory()->create();
        $now = now();

        DB::table('follows')->insert($followers->map(static fn (User $follower): array => [
            'follower_id' => $follower->id,
            'following_id' => $profileOwner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $response = $this->actingAs($viewer)->get(route('user.profile', $profileOwner));

        $response->assertOk()
            ->assertSee(__('profile.and_more', ['count' => 3]))
            ->assertDontSee(__('profile.and_more', ['count' => 2]));
    }

    public function test_profile_relationship_count_uses_a_singular_english_follower_label(): void
    {
        $this->app->setLocale('en');
        $profileOwner = User::factory()->create(['locale' => 'en']);
        $follower = User::factory()->create();
        DB::table('follows')->insert([
            'follower_id' => $follower->id,
            'following_id' => $profileOwner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($profileOwner)->get(route('user.profile', $profileOwner));
        $response->assertOk();

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML((string) $response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $followersLink = $xpath->query('//*[@class="skuy-profile-meta__followers"]')->item(0);

        $this->assertNotNull($followersLink);
        $this->assertSame('1 follower', preg_replace('/\s+/', ' ', trim($followersLink->textContent)));
    }

    public function test_profile_recommendations_filter_followed_users_without_materializing_the_id_list(): void
    {
        $profileOwner = User::factory()->create();
        $viewer = User::factory()->create();
        $followedAccount = User::factory()->create();
        $suggestedAccount = User::factory()->create();
        $viewer->followings()->attach($followedAccount->id);
        $followedAccount->posts()->create(['content' => 'Post from a followed account']);
        $suggestedAccount->posts()->create(['content' => 'Post from an eligible suggestion']);

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($viewer)->get(route('user.profile', $profileOwner));
        $response->assertOk()
            ->assertViewHas('similarAccounts', static fn ($accounts): bool => $accounts->contains('id', $suggestedAccount->id)
                && ! $accounts->contains('id', $followedAccount->id));

        $materializedFollowingIdsQueries = array_values(array_filter(
            $queries,
            static fn (string $sql): bool => preg_match('/^select\s+"users"\."id"\s+from\s+"users"\s+inner join\s+"follows"/i', $sql) === 1,
        ));
        $this->assertSame([], $materializedFollowingIdsQueries);
    }

    public function test_profile_pin_indicator_is_in_the_author_content_column(): void
    {
        $owner = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Pinned profile post for layout coverage.',
        ]);
        $post->forceFill(['pinned_at' => now()])->save();
        $response = $this->actingAs($owner)->get(route('user.profile', $owner));
        $response->assertOk()->assertSee('data-pinned-post="'.$post->uid.'"', false);
        $html = $response->getContent();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $pin = $xpath->query('//*[@data-pinned-post="'.$post->uid.'"]')->item(0);
        $pinParent = $pin?->parentNode;

        $this->assertSame('skuy-post__body', $pinParent instanceof \DOMElement ? $pinParent->getAttribute('class') : null);
    }

    public function test_guest_profile_pin_indicator_is_in_the_author_content_column(): void
    {
        $owner = User::factory()->create();
        $post = Post::create([
            'user_id' => $owner->id,
            'content' => 'Pinned public profile post for layout coverage.',
        ]);
        $post->forceFill(['pinned_at' => now()])->save();
        $response = $this->get(route('user.profile', $owner));
        $response->assertOk()->assertSee('data-pinned-post="'.$post->uid.'"', false);
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $pin = $xpath->query('//*[@data-pinned-post="'.$post->uid.'"]')->item(0);
        $pinParent = $pin?->parentNode;

        $this->assertSame('skuy-post__body', $pinParent instanceof \DOMElement ? $pinParent->getAttribute('class') : null);
    }

    public function test_guest_profile_relationship_rail_hides_members_only_accounts(): void
    {
        $profileOwner = User::factory()->create(['username' => 'guest.rail.owner']);
        $publicFollower = User::factory()->create(['name' => 'Visible Follower', 'username' => 'guest.visible.follower']);
        $privateFollower = User::factory()->create(['name' => 'Hidden Follower', 'username' => 'guest.hidden.follower']);
        $publicFollowing = User::factory()->create(['name' => 'Visible Following', 'username' => 'guest.visible.following']);
        $privateFollowing = User::factory()->create(['name' => 'Hidden Following', 'username' => 'guest.hidden.following']);
        $privateFollower->forceFill(['profile_visibility' => ProfileVisibility::MembersOnly])->save();
        $privateFollowing->forceFill(['profile_visibility' => ProfileVisibility::MembersOnly])->save();

        DB::table('follows')->insert([
            ['follower_id' => $publicFollower->id, 'following_id' => $profileOwner->id, 'created_at' => now(), 'updated_at' => now()],
            ['follower_id' => $privateFollower->id, 'following_id' => $profileOwner->id, 'created_at' => now(), 'updated_at' => now()],
            ['follower_id' => $profileOwner->id, 'following_id' => $publicFollowing->id, 'created_at' => now(), 'updated_at' => now()],
            ['follower_id' => $profileOwner->id, 'following_id' => $privateFollowing->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get(route('user.profile', $profileOwner));

        $response->assertOk()
            ->assertSee($publicFollower->username)
            ->assertSee($publicFollowing->username)
            ->assertDontSee($privateFollower->username)
            ->assertDontSee($privateFollower->name)
            ->assertDontSee($privateFollowing->username)
            ->assertDontSee($privateFollowing->name)
            ->assertViewHas('profileFollowers', fn ($users): bool => $users->contains('id', $publicFollower->id) && ! $users->contains('id', $privateFollower->id))
            ->assertViewHas('profileFollowings', fn ($users): bool => $users->contains('id', $publicFollowing->id) && ! $users->contains('id', $privateFollowing->id));
    }

    public function test_authenticated_profile_relationship_rail_can_show_members_only_accounts(): void
    {
        $profileOwner = User::factory()->create(['username' => 'members.rail.owner']);
        $privateFollower = User::factory()->create(['name' => 'Member Follower', 'username' => 'members.only.follower']);
        $privateFollowing = User::factory()->create(['name' => 'Member Following', 'username' => 'members.only.following']);
        $viewer = User::factory()->create();
        $privateFollower->forceFill(['profile_visibility' => ProfileVisibility::MembersOnly])->save();
        $privateFollowing->forceFill(['profile_visibility' => ProfileVisibility::MembersOnly])->save();

        DB::table('follows')->insert([
            ['follower_id' => $privateFollower->id, 'following_id' => $profileOwner->id, 'created_at' => now(), 'updated_at' => now()],
            ['follower_id' => $profileOwner->id, 'following_id' => $privateFollowing->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($viewer)->get(route('user.profile', $profileOwner));

        $response->assertOk()
            ->assertSee($privateFollower->username)
            ->assertSee($privateFollowing->username)
            ->assertViewHas('profileFollowers', fn ($users): bool => $users->contains('id', $privateFollower->id))
            ->assertViewHas('profileFollowings', fn ($users): bool => $users->contains('id', $privateFollowing->id));
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
            ->assertDontSee('class="skuy-profile-verified"', false)
            ->assertDontSee('data-verified-badge', false);
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
            ->assertSee('data-account-menu-key="likes"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('data-profile-tab="likes"', false)
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
            ->assertSee('skuy-feed-post', false)
            ->assertSee('data-feed-post-uid="'.$post->uid.'"', false)
            ->assertSee('data-feed-post-action="like"', false)
            ->assertSee('data-media-slider="true"', false)
            ->assertSee('data-media-slide="true"', false)
            ->assertSee('media/first.jpg', false)
            ->assertSee('media/second.jpg', false)
            ->assertSee(route('posts.show', $post), false);
    }

    public function test_profile_stream_reuses_feed_media_geometry_without_covering_single_images(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertIsString($css);
        $rules = [
            '.skuy-profile-post-list .skuy-feed-post__media {' => ['width: 100%;', 'margin: 8px 0;'],
            '.skuy-profile-post-list .skuy-feed-post__media .skuy-media-item {' => ['aspect-ratio: auto;', 'width: fit-content;'],
            '.skuy-profile-post-list .skuy-feed-post__media--carousel .skuy-feed-media-item--portrait-preview {' => ['aspect-ratio: 4 / 5;', 'overflow: hidden;'],
            '.skuy-profile-post-list .skuy-feed-post__media .skuy-feed-media-track--single .skuy-media-item {' => ['max-height: 28rem;', 'height: auto;'],
            '.skuy-profile-post-list .skuy-feed-post__media .skuy-feed-media-track--single .skuy-media-item img {' => ['object-fit: contain;', 'max-height: 28rem;'],
        ];

        foreach ($rules as $selector => $declarations) {
            $ruleStart = strpos($css, $selector);
            $this->assertNotFalse($ruleStart, "Missing profile media rule: {$selector}");
            $ruleEnd = strpos($css, '}', $ruleStart);
            $this->assertNotFalse($ruleEnd, "Unclosed profile media rule: {$selector}");
            $rule = substr($css, $ruleStart, $ruleEnd - $ruleStart + 1);

            foreach ($declarations as $declaration) {
                $this->assertStringContainsString($declaration, $rule);
            }
        }
    }

    public function test_profile_repost_uses_original_post_viewer_interaction_state_without_leaking_other_appreciations(): void
    {
        $profileOwner = User::factory()->create(['username' => 'profile.reposter']);
        $originalAuthor = User::factory()->create();
        $otherAppreciator = User::factory()->create();
        $original = Post::create([
            'user_id' => $originalAuthor->id,
            'content' => 'Original profile repost interaction state.',
        ]);
        $repost = Post::create([
            'user_id' => $profileOwner->id,
            'content' => null,
            'repost_id' => $original->id,
        ]);
        Like::create(['post_id' => $original->id, 'user_id' => $profileOwner->id]);
        Bookmark::create(['post_id' => $original->id, 'user_id' => $profileOwner->id]);
        DB::table('post_appreciations')->insert([
            'post_id' => $original->id,
            'user_id' => $otherAppreciator->id,
            'type' => 'bermanfaat',
            'points_spent' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($profileOwner)->get(route('user.profile', $profileOwner));
        $response->assertOk();
        $listedRepost = $response->viewData('posts')->first();
        $displayPost = $listedRepost->originalPost;

        $this->assertSame($repost->id, $listedRepost->id);
        $this->assertSame($original->id, $displayPost->id);
        $this->assertSame(1, $displayPost->likes_count);
        $this->assertTrue((bool) $displayPost->liked_by_me);
        $this->assertSame(1, $displayPost->reposts_count);
        $this->assertTrue((bool) $displayPost->reposted_by_me);
        $this->assertTrue((bool) $displayPost->bookmarked_by_me);
        $this->assertSame(1, $displayPost->appreciations_count);
        $this->assertTrue($displayPost->relationLoaded('appreciations'));
        $this->assertCount(0, $displayPost->appreciations, 'The display post must include only the viewer\'s appreciation state.');
        $this->assertTrue($displayPost->relationLoaded('feedComments'));

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $card = $xpath->query('//article[@data-feed-thread-key="'.$repost->uid.'"]')->item(0);
        $this->assertNotNull($card);
        $this->assertSame('true', $xpath->evaluate('string(.//*[@data-feed-post-action="like"]/@aria-pressed)', $card));
        $this->assertSame('true', $xpath->evaluate('string(.//*[@data-feed-post-action="repost"]/@aria-pressed)', $card));
        $this->assertSame('true', $xpath->evaluate('string(.//*[@data-feed-post-action="bookmark"]/@aria-pressed)', $card));
        $this->assertSame(1, $xpath->query('.//*[@data-post-appreciation-trigger]', $card)->length, 'Another user\'s appreciation must not be presented as the viewer\'s.');
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
