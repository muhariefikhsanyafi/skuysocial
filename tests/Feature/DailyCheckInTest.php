<?php

namespace Tests\Feature;

use App\Actions\ApplyPointTransaction;
use App\Actions\ClaimDailyCheckIn;
use App\Enums\ModerationState;
use App\Enums\PointTransactionType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use OverflowException;
use Tests\TestCase;

class DailyCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_checkin_awards_ten_points_with_a_deterministic_ledger_identity(): void
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::now((string) config('app.timezone'))->toDateString();

        $response = $this->actingAs($user)->post(route('daily-checkin.store'), [
            'amount' => 999,
            'checkin_date' => '1999-01-01',
        ]);

        $response->assertRedirect(route('dashboard'))
            ->assertSessionHas('daily_checkin_claimed', true);

        $this->assertDatabaseHas('daily_checkins', [
            'user_id' => $user->getKey(),
            'checkin_date' => $date,
        ]);
        $this->assertSame(1, DB::table('daily_checkins')->where('user_id', $user->getKey())->count());

        $walletId = DB::table('point_wallets')->where('user_id', $user->getKey())->value('id');
        $this->assertDatabaseHas('point_transactions', [
            'wallet_id' => $walletId,
            'type' => PointTransactionType::Credit->value,
            'amount' => 10,
            'reason_code' => 'daily_checkin',
            'reference_type' => 'daily_checkin',
        ]);
        $transaction = DB::table('point_transactions')->where('wallet_id', $walletId)->first();
        $this->assertNotNull($transaction);
        $this->assertMatchesRegularExpression('/\Av1-[a-f0-9]{64}\z/', $transaction->reference_key);
        $this->assertSame($transaction->reference_key, $transaction->idempotency_key);
        $this->assertSame(10, (int) DB::table('point_wallets')->where('id', $walletId)->value('balance'));
        $this->assertDatabaseCount('point_transactions', 1);
    }

    public function test_duplicate_same_day_post_reports_already_claimed_without_another_award(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('daily-checkin.store'))->assertRedirect(route('dashboard'));

        $duplicate = $this->post(route('daily-checkin.store'));

        $duplicate->assertRedirect(route('dashboard'))
            ->assertSessionHas('daily_checkin_claimed', false);
        $this->assertSame(1, DB::table('daily_checkins')->where('user_id', $user->getKey())->count());
        $this->assertSame(1, DB::table('point_transactions')->count());
        $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
        $this->get(route('dashboard'))->assertSee(__('home.dashboard.checkin.already_claimed'));
    }

    public function test_replaying_the_deterministic_ledger_identity_resolves_the_original_credit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('daily-checkin.store'));
        $original = DB::table('point_transactions')->first();

        $replay = app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            10,
            'daily_checkin',
            'daily_checkin',
            $original->reference_key,
            $original->idempotency_key,
        );

        $this->assertSame((int) $original->id, (int) $replay->getKey());
        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
    }

    public function test_same_day_checkins_are_scoped_to_each_users_wallet(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->post(route('daily-checkin.store'));
        $firstTransaction = DB::table('point_transactions')->first();
        $firstWalletId = $firstUser->pointWallet()->value('id');

        $this->actingAs($secondUser)->post(route('daily-checkin.store'));
        $secondTransaction = DB::table('point_transactions')
            ->where('wallet_id', $secondUser->pointWallet()->value('id'))
            ->first();

        $this->assertNotSame((int) $firstWalletId, (int) $secondUser->pointWallet()->value('id'));
        $this->assertSame($firstTransaction->reference_key, $secondTransaction->reference_key);
        $this->assertDatabaseCount('daily_checkins', 2);
        $this->assertDatabaseCount('point_transactions', 2);
        $this->assertSame(10, (int) $firstUser->pointWallet()->value('balance'));
        $this->assertSame(10, (int) $secondUser->pointWallet()->value('balance'));
    }

    public function test_user_can_check_in_again_on_the_next_application_day(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $user = User::factory()->create();

        try {
            $this->travelTo(CarbonImmutable::parse('2026-10-04 03:00:00 UTC'));
            $this->actingAs($user)->post(route('daily-checkin.store'));
            $this->assertDatabaseHas('daily_checkins', [
                'user_id' => $user->getKey(),
                'checkin_date' => '2026-10-04',
            ]);

            $this->travelTo(CarbonImmutable::parse('2026-10-05 03:00:00 UTC'));
            $this->post(route('daily-checkin.store'));
        } finally {
            $this->travelBack();
        }

        $this->assertDatabaseCount('daily_checkins', 2);
        $this->assertDatabaseCount('point_transactions', 2);
        $this->assertSame(20, (int) $user->pointWallet()->value('balance'));
    }

    public function test_application_timezone_sets_the_date_and_request_fields_cannot_override_reward_or_date(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $user = User::factory()->create();

        try {
            $this->travelTo(CarbonImmutable::parse('2026-10-04 17:30:00 UTC'));

            $this->actingAs($user)->post(route('daily-checkin.store'), [
                'amount' => 1,
                'checkin_date' => '2026-10-04',
            ]);
        } finally {
            $this->travelBack();
        }

        $this->assertDatabaseHas('daily_checkins', [
            'user_id' => $user->getKey(),
            'checkin_date' => '2026-10-05',
        ]);
        $this->assertDatabaseMissing('daily_checkins', [
            'user_id' => $user->getKey(),
            'checkin_date' => '2026-10-04',
        ]);
        $this->assertSame(10, (int) DB::table('point_transactions')->sum('amount'));
        $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
    }

    public function test_checkin_row_rolls_back_if_the_points_credit_cannot_be_applied(): void
    {
        $user = User::factory()->create();
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            PHP_INT_MAX,
            'test_credit',
            'test',
            'max-balance',
            'r6b:test:max-balance',
        );

        try {
            app(ClaimDailyCheckIn::class)->handle($user);
            $this->fail('The overflowing credit must fail and roll back the check-in row.');
        } catch (OverflowException) {
            $this->assertDatabaseCount('daily_checkins', 0);
            $this->assertDatabaseCount('point_transactions', 1);
            $this->assertSame(PHP_INT_MAX, (int) $user->pointWallet()->value('balance'));
        }
    }

    public function test_dashboard_and_get_requests_are_read_only(): void
    {
        $storeRoute = Route::getRoutes()->getByName('daily-checkin.store');
        $this->assertNotNull($storeRoute);
        $this->assertContains('POST', $storeRoute->methods());
        $this->assertContains('auth', $storeRoute->gatherMiddleware());
        $this->assertContains('verified', $storeRoute->gatherMiddleware());
        $this->assertContains('moderation.active', $storeRoute->gatherMiddleware());
        $routeMiddleware = app('router')->gatherRouteMiddleware($storeRoute);
        $this->assertContains(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, $routeMiddleware);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('home.dashboard.checkin.title'))
            ->assertSee(__('home.dashboard.checkin.available'))
            ->assertSee('data-daily-checkin-date="'.CarbonImmutable::now((string) config('app.timezone'))->toDateString().'"', false)
            ->assertSee('action="'.route('daily-checkin.store').'"', false);

        $this->get(route('daily-checkin.store'))->assertStatus(405);
        $this->assertDatabaseCount('daily_checkins', 0);
        $this->assertDatabaseCount('point_wallets', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_guest_and_unverified_users_cannot_claim_points(): void
    {
        $this->post(route('daily-checkin.store'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post(route('daily-checkin.store'))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('daily_checkins', 0);
        $this->assertDatabaseCount('point_wallets', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_restricted_user_cannot_claim_but_keeps_the_existing_read_surface(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['moderation_state' => ModerationState::Restricted->value])->save();

        $this->actingAs($user)->post(route('daily-checkin.store'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseCount('daily_checkins', 0);
        $this->assertDatabaseCount('point_wallets', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_daily_checkin_dashboard_state_and_copy_are_localized_in_both_locales(): void
    {
        foreach (['en', 'id'] as $locale) {
            $user = User::factory()->create(['locale' => $locale]);

            $available = $this->actingAs($user)->get(route('dashboard'));
            $available->assertOk()
                ->assertSee(__('home.dashboard.checkin.title', [], $locale))
                ->assertSee(__('home.dashboard.checkin.available', [], $locale))
                ->assertSee(__('home.dashboard.checkin.claim', ['amount' => 10], $locale))
                ->assertSee('+10 '.__('points.unit', [], $locale));

            $claim = $this->post(route('daily-checkin.store'));
            $claim->assertRedirect(route('dashboard'))
                ->assertSessionHas('daily_checkin_claimed', true);

            $this->get(route('dashboard'))
                ->assertOk()
                ->assertSee(__('home.dashboard.checkin.received', ['amount' => 10], $locale))
                ->assertSee(__('home.dashboard.checkin.checked_in', [], $locale))
                ->assertSee('data-points-balance="10"', false)
                ->assertDontSee('action="'.route('daily-checkin.store').'"', false);

            $this->get(route('points.history'))
                ->assertOk()
                ->assertSee(__('points.reasons.daily_checkin', [], $locale));
        }
    }

    public function test_database_unique_constraint_enforces_one_checkin_per_user_and_date(): void
    {
        $user = User::factory()->create();
        $row = [
            'user_id' => $user->getKey(),
            'checkin_date' => CarbonImmutable::now((string) config('app.timezone'))->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('daily_checkins')->insert($row);

        try {
            DB::table('daily_checkins')->insert($row);
            $this->fail('The user/date unique constraint must reject a duplicate check-in.');
        } catch (QueryException) {
            $this->assertDatabaseCount('daily_checkins', 1);
        }
    }
}
