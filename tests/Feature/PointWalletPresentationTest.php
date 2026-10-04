<?php

namespace Tests\Feature;

use App\Actions\ApplyPointTransaction;
use App\Enums\ModerationState;
use App\Enums\PointTransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PointWalletPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_settings_show_zero_wallet_without_creating_wallet_or_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit', $user->username));

        $response->assertOk()
            ->assertSee(__('points.wallet.heading'))
            ->assertSee('data-points-balance="0"', false)
            ->assertSee(route('points.history'), false);

        $this->assertDatabaseCount('point_wallets', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_wallet_summary_is_localized_for_indonesian_and_english_accounts(): void
    {
        foreach (['id', 'en'] as $locale) {
            $user = User::factory()->create(['locale' => $locale]);
            $response = $this->actingAs($user)->get(route('profile.edit', $user->username));

            $response->assertOk()
                ->assertSee(__('points.wallet.heading', [], $locale))
                ->assertSee(__('points.wallet.balance_label', [], $locale))
                ->assertSee(__('points.wallet.history_link', [], $locale));

            $this->get(route('points.history'))
                ->assertOk()
                ->assertSee(__('points.history.title', [], $locale))
                ->assertSee(__('points.history.empty_heading', [], $locale));
        }
    }

    public function test_owner_history_is_stable_paginated_and_does_not_expose_internal_keys(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ids = [];

        for ($index = 1; $index <= 21; $index++) {
            $transaction = $ledger->handle(
                $user,
                PointTransactionType::Credit,
                $index,
                'test_credit',
                'test',
                'history-'.$index,
                'r6a:test:history:'.$index,
            );
            $ids[] = $transaction->getKey();
        }

        $pageOne = $this->actingAs($user)->get(route('points.history'));
        $pageOne->assertOk()
            ->assertSee(__('points.history.title'))
            ->assertSee(__('points.transaction_types.credit'))
            ->assertSee('href="'.route('points.history', ['page' => 2]).'"', false)
            ->assertDontSee('r6a:test:history:')
            ->assertDontSee('history-21');

        $firstPageTransactions = $pageOne->viewData('transactions')->getCollection();
        $this->assertCount(20, $firstPageTransactions);
        $this->assertSame(array_reverse(array_slice($ids, 1)), $firstPageTransactions->pluck('id')->all());

        $pageTwo = $this->get(route('points.history', ['page' => 2]));
        $pageTwo->assertOk();
        $this->assertSame([$ids[0]], $pageTwo->viewData('transactions')->getCollection()->pluck('id')->all());
    }

    public function test_history_read_is_owner_scoped_and_query_fields_cannot_change_a_wallet(): void
    {
        $owner = User::factory()->create();
        app(ApplyPointTransaction::class)->handle(
            $owner,
            PointTransactionType::Credit,
            40,
            'test_credit',
            'test',
            'private-history',
            'r6a:test:private',
        );
        $reader = User::factory()->create();

        $response = $this->actingAs($reader)->get(route('points.history', [
            'user_id' => $owner->getKey(),
            'beneficiary_id' => $owner->getKey(),
            'amount' => 999999,
        ]));

        $response->assertOk()
            ->assertSee('data-points-balance="0"', false)
            ->assertDontSee('private-history')
            ->assertDontSee('40 Points');

        $this->assertDatabaseCount('point_wallets', 1);
        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertNull($reader->fresh()->pointWallet);
    }

    public function test_guest_and_unverified_users_cannot_read_wallet_history(): void
    {
        $this->get(route('points.history'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->get(route('points.history'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_restricted_account_can_read_its_own_wallet_history(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['moderation_state' => ModerationState::Restricted->value])->save();
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            3,
            'test_credit',
            'test',
            'restricted-owner',
            'r6a:test:restricted',
        );

        $this->actingAs($user)->get(route('points.history'))
            ->assertOk()
            ->assertSee('data-points-balance="3"', false);
    }

    public function test_public_profile_never_shows_private_points_balance_or_history(): void
    {
        $user = User::factory()->create();
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            47,
            'test_credit',
            'test',
            'private-profile',
            'r6a:test:profile-private',
        );

        $this->actingAs($user)->get(route('user.profile', $user))
            ->assertOk()
            ->assertDontSee(__('points.wallet.heading'))
            ->assertDontSee('data-points-balance', false)
            ->assertDontSee('47 Points');
    }

    public function test_account_deletion_detaches_wallet_and_reregistration_gets_a_new_wallet_identity(): void
    {
        $user = User::factory()->create([
            'email' => 'wallet.owner@example.test',
            'username' => 'wallet.owner',
        ]);
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            12,
            'test_credit',
            'test',
            'delete-owner-history',
            'r6a:test:delete-owner',
        );
        $walletId = $user->pointWallet()->value('id');

        $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
            'username_confirmation' => $user->username,
        ])->assertRedirect('/');

        $this->assertDatabaseHas('point_wallets', ['id' => $walletId, 'user_id' => null, 'balance' => 12]);
        $this->assertDatabaseHas('point_transactions', [
            'wallet_id' => $walletId,
            'amount' => 12,
            'reference_key' => 'delete-owner-history',
        ]);

        $replacement = User::factory()->create([
            'email' => 'wallet.owner@example.test',
            'username' => 'wallet.owner',
        ]);

        $this->assertNotSame($user->getKey(), $replacement->getKey());
        $this->assertNull($replacement->pointWallet);
        $this->assertDatabaseCount('point_wallets', 1);
        $this->assertDatabaseCount('point_transactions', 1);
    }

    public function test_points_routes_are_read_only_and_do_not_add_navigation_items(): void
    {
        $route = Route::getRoutes()->getByName('points.history');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertFalse(Route::has('points.credit'));
        $this->assertFalse(Route::has('points.debit'));

        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('feed'))->assertOk()->getContent();
        $mobileStart = strpos($html, '<nav class="skuy-mobile-nav"');
        $this->assertNotFalse($mobileStart);
        $mobileEnd = strpos($html, '</nav>', $mobileStart);
        preg_match_all('/data-mobile-nav-key="([^"]+)"/', substr($html, $mobileStart, $mobileEnd - $mobileStart), $keys);
        $this->assertSame(['home', 'search', 'create', 'activity', 'messages'], $keys[1]);
    }
}
