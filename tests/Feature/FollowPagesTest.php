<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FollowPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_followers_page_shows_only_followers_in_newest_follow_order(): void
    {
        $owner = User::factory()->create(['username' => 'followers-owner']);
        $older = User::factory()->create(['username' => 'older-follower']);
        $firstTie = User::factory()->create(['username' => 'first-tie-follower']);
        $secondTie = User::factory()->create(['username' => 'second-tie-follower']);
        $unrelated = User::factory()->create(['username' => 'unrelated-user']);

        $this->createFollow($older, $owner, '2026-09-01 10:00:00');
        $this->createFollow($firstTie, $owner, '2026-09-01 11:00:00');
        $this->createFollow($secondTie, $owner, '2026-09-01 11:00:00');

        $response = $this->actingAs($owner)->get(route('user.followers', $owner));

        $response
            ->assertOk()
            ->assertViewIs('follow-list')
            ->assertSee('Pengikut')
            ->assertSee(route('user.profile', $secondTie), false)
            ->assertDontSee(route('user.profile', $unrelated), false);

        $users = $response->viewData('users');

        $this->assertSame(
            [$secondTie->id, $firstTie->id, $older->id],
            $users->getCollection()->pluck('id')->all(),
        );
    }

    public function test_following_page_shows_only_followings_in_newest_follow_order(): void
    {
        $owner = User::factory()->create(['username' => 'following-owner']);
        $older = User::factory()->create(['username' => 'older-following']);
        $newer = User::factory()->create(['username' => 'newer-following']);
        $unrelated = User::factory()->create(['username' => 'unrelated-following']);

        $this->createFollow($owner, $older, '2026-09-01 10:00:00');
        $this->createFollow($owner, $newer, '2026-09-01 11:00:00');

        $response = $this->actingAs($owner)->get(route('user.following', $owner));

        $response
            ->assertOk()
            ->assertViewIs('follow-list')
            ->assertSee(route('user.profile', $newer), false)
            ->assertDontSee(route('user.profile', $unrelated), false);

        $users = $response->viewData('users');

        $this->assertSame(
            [$newer->id, $older->id],
            $users->getCollection()->pluck('id')->all(),
        );
    }

    public function test_followers_page_is_bounded_to_fifteen_users(): void
    {
        $owner = User::factory()->create(['username' => 'paged-followers-owner']);

        for ($index = 0; $index < 16; $index++) {
            $follower = User::factory()->create(['username' => "paged-follower-{$index}"]);
            $this->createFollow($follower, $owner, Carbon::now()->addSeconds($index)->toDateTimeString());
        }

        $firstPage = $this->actingAs($owner)
            ->get(route('user.followers', $owner))
            ->viewData('users');
        $secondPage = $this->actingAs($owner)
            ->get(route('user.followers', $owner).'?page=2')
            ->viewData('users');

        $this->assertInstanceOf(Paginator::class, $firstPage);
        $this->assertCount(15, $firstPage->items());
        $this->assertTrue($firstPage->hasMorePages());
        $this->assertCount(1, $secondPage->items());
    }

    public function test_following_page_is_bounded_to_fifteen_users(): void
    {
        $owner = User::factory()->create(['username' => 'paged-following-owner']);

        for ($index = 0; $index < 16; $index++) {
            $following = User::factory()->create(['username' => "paged-following-{$index}"]);
            $this->createFollow($owner, $following, Carbon::now()->addSeconds($index)->toDateTimeString());
        }

        $firstPage = $this->actingAs($owner)
            ->get(route('user.following', $owner))
            ->viewData('users');
        $secondPage = $this->actingAs($owner)
            ->get(route('user.following', $owner).'?page=2')
            ->viewData('users');

        $this->assertInstanceOf(Paginator::class, $firstPage);
        $this->assertCount(15, $firstPage->items());
        $this->assertTrue($firstPage->hasMorePages());
        $this->assertCount(1, $secondPage->items());
    }

    public function test_profile_following_and_followers_counts_link_to_their_pages(): void
    {
        $owner = User::factory()->create(['username' => 'count-links-owner']);

        $response = $this->actingAs($owner)->get(route('user.profile', $owner));

        $response
            ->assertOk()
            ->assertSee(route('user.following', $owner), false)
            ->assertSee(route('user.followers', $owner), false);
    }

    public function test_follow_state_is_prepared_once_for_listed_users_and_self_action_is_hidden(): void
    {
        $owner = User::factory()->create(['username' => 'state-owner']);
        $viewer = User::factory()->create(['username' => 'state-viewer']);
        $followedUser = User::factory()->create(['username' => 'state-followed']);
        $unfollowedUser = User::factory()->create(['username' => 'state-unfollowed']);

        $this->createFollow($followedUser, $owner, '2026-09-01 10:00:00');
        $this->createFollow($unfollowedUser, $owner, '2026-09-01 09:00:00');
        $this->createFollow($viewer, $followedUser, '2026-09-01 08:00:00');
        $this->createFollow($viewer, $owner, '2026-09-01 07:00:00');

        $response = $this->actingAs($viewer)->get(route('user.followers', $owner));
        $followedUserIds = $response->viewData('followedUserIds');

        $response
            ->assertOk()
            ->assertSee('Mengikuti')
            ->assertSee('Ikuti')
            ->assertDontSee(route('user.follow', $viewer), false);
        $this->assertTrue($followedUserIds->contains($followedUser->id));
        $this->assertFalse($followedUserIds->contains($unfollowedUser->id));
    }

    public function test_follow_state_query_count_does_not_grow_with_page_size(): void
    {
        $owner = User::factory()->create(['username' => 'query-owner']);
        $viewer = User::factory()->create(['username' => 'query-viewer']);

        $firstFollower = User::factory()->create(['username' => 'query-follower-0']);
        $this->createFollow($firstFollower, $owner, '2026-09-01 10:00:00');

        $oneUserQueryCount = $this->countFollowersPageQueries($owner, $viewer);

        for ($index = 1; $index < 15; $index++) {
            $follower = User::factory()->create(['username' => "query-follower-{$index}"]);
            $this->createFollow($follower, $owner, '2026-09-01 10:00:00');
        }

        $fifteenUserQueryCount = $this->countFollowersPageQueries($owner, $viewer);

        $this->assertSame($oneUserQueryCount, $fifteenUserQueryCount);
    }

    public function test_follow_pages_show_empty_states(): void
    {
        $owner = User::factory()->create(['username' => 'empty-owner']);

        $this->actingAs($owner)
            ->get(route('user.followers', $owner))
            ->assertOk()
            ->assertSee('Belum ada pengikut');

        $this->actingAs($owner)
            ->get(route('user.following', $owner))
            ->assertOk()
            ->assertSee('Belum mengikuti siapa pun');
    }

    public function test_guests_cannot_access_follow_pages(): void
    {
        $owner = User::factory()->create(['username' => 'guest-owner']);

        $this->get(route('user.followers', $owner))
            ->assertRedirect(route('login'));

        $this->get(route('user.following', $owner))
            ->assertRedirect(route('login'));
    }

    private function createFollow(User $follower, User $following, string $createdAt): void
    {
        DB::table('follows')->insert([
            'follower_id' => $follower->id,
            'following_id' => $following->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function countFollowersPageQueries(User $owner, User $viewer): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($viewer)->get(route('user.followers', $owner));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queryCount;
    }
}
