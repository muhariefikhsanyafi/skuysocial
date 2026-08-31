<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\UserFollowed;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FollowConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_follow_creates_exactly_one_follow_row(): void
    {
        [$actor, $target] = $this->createActorAndTarget();

        $response = $this->actingAs($actor)->post(route('user.follow', $target));

        $response->assertRedirect();
        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseHas('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_second_sequential_toggle_unfollows(): void
    {
        [$actor, $target] = $this->createActorAndTarget();

        $this->actingAs($actor)->post(route('user.follow', $target));
        $response = $this->actingAs($actor)->post(route('user.follow', $target));

        $response->assertRedirect();
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_follow_state_between_users_remains_isolated(): void
    {
        [$actorA, $target] = $this->createActorAndTarget();
        $actorB = User::factory()->create();

        $this->actingAs($actorA)->post(route('user.follow', $target));
        $this->actingAs($actorB)->post(route('user.follow', $target));
        $this->actingAs($actorA)->post(route('user.follow', $target));

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $actorA->id,
            'following_id' => $target->id,
        ]);
        $this->assertDatabaseHas('follows', [
            'follower_id' => $actorB->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_database_rejects_duplicate_follow_pair(): void
    {
        [$actor, $target] = $this->createActorAndTarget();

        DB::table('follows')->insert([
            'follower_id' => $actor->id,
            'following_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('follows')->insert([
            'follower_id' => $actor->id,
            'following_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_self_follow_does_not_create_a_follow_row(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('user.follow', $user));

        $response->assertRedirect();
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_target_receives_exactly_one_notification_after_first_follow(): void
    {
        Notification::fake();
        [$actor, $target] = $this->createActorAndTarget();

        $this->actingAs($actor)->post(route('user.follow', $target));

        Notification::assertSentTo($target, UserFollowed::class);
        $this->assertCount(1, Notification::sent($target, UserFollowed::class));
    }

    public function test_unique_follow_conflict_is_treated_as_success_without_extra_notification(): void
    {
        Notification::fake();
        [$actor, $target] = $this->createActorAndTarget();
        $active = true;
        $matched = false;

        DB::listen(function (QueryExecuted $query) use (&$active, &$matched, $actor, $target): void {
            $sql = strtolower(ltrim($query->sql));

            if (! $active || $matched || ! str_starts_with($sql, 'select') || ! str_contains($sql, 'follows')) {
                return;
            }

            $matched = true;
            DB::table('follows')->insert([
                'follower_id' => $actor->id,
                'following_id' => $target->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $response = $this->actingAs($actor)->post(route('user.follow', $target));
        } finally {
            $active = false;
        }

        $this->assertTrue($matched);
        $response->assertRedirect();
        $this->assertDatabaseCount('follows', 1);
        Notification::assertNothingSent();
    }

    public function test_unrelated_follow_database_errors_are_not_swallowed(): void
    {
        [$actor, $target] = $this->createActorAndTarget();
        $active = true;
        $matched = false;

        DB::listen(function (QueryExecuted $query) use (&$active, &$matched, $actor): void {
            $sql = strtolower(ltrim($query->sql));

            if (! $active || $matched || ! str_starts_with($sql, 'select') || ! str_contains($sql, 'follows')) {
                return;
            }

            $matched = true;
            DB::table('follows')->insert([
                'follower_id' => 999999999,
                'following_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->withoutExceptionHandling();
            $this->expectException(QueryException::class);
            $this->actingAs($actor)->post(route('user.follow', $target));
        } finally {
            $active = false;
        }

        $this->assertTrue($matched);
    }

    public function test_re_follow_after_unfollow_can_be_performed_again(): void
    {
        [$actor, $target] = $this->createActorAndTarget();

        $this->actingAs($actor)->post(route('user.follow', $target));
        $this->actingAs($actor)->post(route('user.follow', $target));
        $this->actingAs($actor)->post(route('user.follow', $target));

        $this->assertDatabaseCount('follows', 1);
        $this->assertDatabaseHas('follows', [
            'follower_id' => $actor->id,
            'following_id' => $target->id,
        ]);
    }

    public function test_follow_route_uses_username_route_key(): void
    {
        [$actor, $target] = $this->createActorAndTarget();

        $route = route('user.follow', $target);

        $this->assertSame('username', $target->getRouteKeyName());
        $this->assertStringContainsString('/user/'.$target->username.'/follow', $route);
        $this->assertStringNotContainsString('/user/'.$target->id.'/follow', $route);
        $this->actingAs($actor)->post($route)->assertRedirect();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function createActorAndTarget(): array
    {
        return [User::factory()->create(), User::factory()->create()];
    }
}
