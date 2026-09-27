<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileLinksPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_a_public_profile(): void
    {
        $profile = User::factory()->create(['username' => 'public.profile']);

        $this->get(route('user.profile', $profile->username))
            ->assertOk()
            ->assertSee('public.profile');
    }

    public function test_new_profiles_default_to_public_visibility(): void
    {
        $profile = User::factory()->create();

        $this->assertSame('public', $profile->fresh()->getRawOriginal('profile_visibility'));
    }

    public function test_members_only_profile_redirects_guests_to_login_with_intended_url(): void
    {
        $profile = User::factory()->create();
        $profile->forceFill(['profile_visibility' => ProfileVisibility::MembersOnly])->save();
        $profileUrl = route('user.profile', $profile->username).'?tab=likes';

        $this->get($profileUrl)
            ->assertRedirect(route('login'));

        $this->assertStringEndsWith($profileUrl, session('url.intended'));
    }

    public function test_profile_settings_save_profile_links_in_submitted_order(): void
    {
        $owner = User::factory()->create(['username' => 'links.owner']);

        $this->actingAs($owner)
            ->post(route('user.update'), [
                'name' => $owner->name,
                'bio' => $owner->bio,
                'profile_links' => [
                    ['label' => '  Portfolio  ', 'url' => 'https://example.test/work'],
                    ['label' => 'Community', 'url' => 'http://community.example.test/u/links.owner'],
                ],
            ])
            ->assertRedirect(route('user.profile', $owner->username));

        $links = $owner->fresh()->profileLinks;

        $this->assertSame(['Portfolio', 'Community'], $links->pluck('label')->all());
        $this->assertSame([0, 1], $links->pluck('sort_order')->all());
    }

    public function test_public_profile_renders_escaped_profile_links_as_safe_external_links(): void
    {
        $profile = User::factory()->create();
        $profile->profileLinks()->create([
            'label' => 'My <portfolio>',
            'url' => 'https://portfolio.example.test/?source=profile&view=public',
            'sort_order' => 0,
        ]);

        $this->actingAs($profile)
            ->get(route('user.profile', $profile->username))
            ->assertOk()
            ->assertSee('My <portfolio>')
            ->assertSee('href="https://portfolio.example.test/?source=profile&amp;view=public"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer nofollow ugc"', false);
    }

    public function test_profile_settings_show_links_and_profile_page_privacy_controls(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('user.edit', $owner->username))
            ->assertOk()
            ->assertSee('name="profile_links_present" value="1"', false)
            ->assertSee('name="profile_visibility"', false)
            ->assertSee('profile-links-heading', false)
            ->assertSee('profile-privacy-heading', false)
            ->assertSee(__('settings.profile.privacy_description'))
            ->assertSee(__('settings.profile.move_link_up'))
            ->assertSee(__('settings.profile.move_link_down'));
    }

    public function test_profile_settings_explain_profile_page_privacy_in_english(): void
    {
        $this->app->setLocale('en');
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('user.edit', $owner->username))
            ->assertOk()
            ->assertSee(__('settings.profile.privacy_description'))
            ->assertSee(__('settings.profile.privacy_public_description'))
            ->assertSee(__('settings.profile.privacy_members_only_description'));
    }

    public function test_empty_link_list_is_preserved_after_a_validation_redirect(): void
    {
        $owner = User::factory()->create();
        $owner->profileLinks()->create([
            'label' => 'Existing',
            'url' => 'https://existing.example.test',
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->from(route('user.edit', $owner->username))
            ->followingRedirects()
            ->post(route('user.update'), [
                'name' => '',
                'profile_links_present' => '1',
            ])
            ->assertOk()
            ->assertSee('profileLinks: []', false);
    }

    public function test_invalid_url_does_not_replace_existing_profile_links(): void
    {
        $owner = User::factory()->create();
        $owner->profileLinks()->create([
            'label' => 'Existing',
            'url' => 'https://existing.example.test',
            'sort_order' => 0,
        ]);

        $this->actingAs($owner)
            ->from(route('user.edit', $owner->username))
            ->post(route('user.update'), [
                'name' => $owner->name,
                'profile_links_present' => '1',
                'profile_links' => [
                    ['label' => 'Unsafe', 'url' => 'javascript:alert(1)'],
                ],
            ])
            ->assertSessionHasErrors('profile_links.0.url');

        $this->assertSame(['Existing'], $owner->fresh()->profileLinks->pluck('label')->all());
    }

    public function test_five_profile_links_are_accepted(): void
    {
        $owner = User::factory()->create();
        $links = [];

        for ($index = 0; $index < 5; $index++) {
            $links[] = ['label' => "Link {$index}", 'url' => "https://link{$index}.example.test"];
        }

        $this->actingAs($owner)
            ->post(route('user.update'), [
                'name' => $owner->name,
                'profile_links_present' => '1',
                'profile_links' => $links,
            ])
            ->assertSessionHasNoErrors();

        $savedLinks = $owner->fresh()->profileLinks;

        $this->assertCount(5, $savedLinks);
        $this->assertSame([0, 1, 2, 3, 4], $savedLinks->pluck('sort_order')->all());
    }

    public function test_more_than_five_links_does_not_replace_existing_profile_links(): void
    {
        $owner = User::factory()->create();
        $owner->profileLinks()->create([
            'label' => 'Existing',
            'url' => 'https://existing.example.test',
            'sort_order' => 0,
        ]);
        $links = [];

        for ($index = 0; $index < 6; $index++) {
            $links[] = ['label' => "Link {$index}", 'url' => "https://link{$index}.example.test"];
        }

        $this->actingAs($owner)
            ->from(route('user.edit', $owner->username))
            ->post(route('user.update'), [
                'name' => $owner->name,
                'profile_links_present' => '1',
                'profile_links' => $links,
            ])
            ->assertSessionHasErrors('profile_links');

        $this->assertSame(['Existing'], $owner->fresh()->profileLinks->pluck('label')->all());
    }

    public function test_profile_settings_can_select_members_only_visibility(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('user.update'), [
                'name' => $owner->name,
                'profile_visibility' => ProfileVisibility::MembersOnly->value,
                'profile_links_present' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.profile', $owner->username));

        $this->assertSame(ProfileVisibility::MembersOnly, $owner->fresh()->profile_visibility);
    }

    public function test_authenticated_members_and_profile_owner_can_view_members_only_profiles(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['profile_visibility' => ProfileVisibility::MembersOnly])->save();
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('user.profile', $owner->username))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('user.profile', $owner->username))
            ->assertOk();
    }

    public function test_profile_link_replacement_rolls_back_if_a_new_link_cannot_be_saved(): void
    {
        $owner = User::factory()->create(['name' => 'Original Name']);
        $owner->profileLinks()->create([
            'label' => 'Keep this link',
            'url' => 'https://existing.example.test',
            'sort_order' => 0,
        ]);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_second_profile_link
            BEFORE INSERT ON user_profile_links
            WHEN NEW.sort_order = 1
            BEGIN
                SELECT RAISE(ABORT, 'forced profile link insert failure');
            END
            SQL);

        $this->withoutExceptionHandling();
        $failed = false;

        try {
            $this->actingAs($owner)->post(route('user.update'), [
                'name' => 'Changed Name',
                'profile_links_present' => '1',
                'profile_links' => [
                    ['label' => 'New first', 'url' => 'https://first.example.test'],
                    ['label' => 'New second', 'url' => 'https://second.example.test'],
                ],
            ]);
        } catch (QueryException) {
            $failed = true;
        }

        $this->assertTrue($failed);
        $freshOwner = $owner->fresh();
        $this->assertSame('Original Name', $freshOwner->name);
        $this->assertSame(['Keep this link'], $freshOwner->profileLinks->pluck('label')->all());
    }
}
