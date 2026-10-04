<?php

namespace Tests\Feature;

use App\Actions\ApplyPointTransaction;
use App\Actions\InsufficientPointBalance;
use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use OverflowException;
use Tests\TestCase;

class PointWalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_credit_creates_a_wallet_and_matching_ledger_entry(): void
    {
        $user = User::factory()->create();

        $transaction = app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            10,
            'test_credit',
            'test',
            'test-1',
            'r6a-test:credit:1',
        );

        $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
        $this->assertSame(10, (int) $transaction->amount);
        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(10, (int) DB::table('point_transactions')->sum('amount'));
    }

    public function test_debit_spends_points_without_creating_a_recipient_credit(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ledger->handle($user, PointTransactionType::Credit, 20, 'test_credit', 'test', 'credit-1', 'r6a:test:credit:1');

        $debit = $ledger->handle($user, PointTransactionType::Debit, 7, 'test_spend', 'test', 'spend-1', 'r6a:test:spend:1');

        $this->assertSame(-7, (int) $debit->amount);
        $this->assertSame(13, (int) $user->pointWallet()->value('balance'));
        $this->assertSame(13, (int) DB::table('point_transactions')->sum('amount'));
        $this->assertDatabaseCount('point_wallets', 1);
    }

    public function test_exact_replay_returns_the_original_transaction_without_a_second_effect(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $arguments = [$user, PointTransactionType::Credit, 10, 'test_credit', 'test', 'same-ref', 'r6a:test:credit:replay'];

        $first = $ledger->handle(...$arguments);
        $replay = $ledger->handle(...$arguments);

        $this->assertSame($first->getKey(), $replay->getKey());
        $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
        $this->assertDatabaseCount('point_transactions', 1);
    }

    public function test_reusing_an_idempotency_key_with_changed_data_is_rejected(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ledger->handle($user, PointTransactionType::Credit, 10, 'test_credit', 'test', 'same-ref', 'r6a:test:credit:key');

        try {
            $ledger->handle($user, PointTransactionType::Credit, 11, 'test_credit', 'test', 'same-ref', 'r6a:test:credit:key');
            $this->fail('Changed transaction data must not reuse an idempotency key.');
        } catch (DomainException) {
            $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
            $this->assertDatabaseCount('point_transactions', 1);
        }
    }

    public function test_zero_and_non_integer_amounts_are_rejected_without_creating_a_wallet(): void
    {
        $user = User::factory()->create();

        foreach ([0, -1, 1.5, '2', true] as $index => $amount) {
            try {
                app(ApplyPointTransaction::class)->handle(
                    $user,
                    PointTransactionType::Credit,
                    $amount,
                    'test_credit',
                    'test',
                    'invalid-'.$index,
                    'r6a:test:invalid:'.$index,
                );
                $this->fail('Invalid point amounts must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('point_wallets', 0);
                $this->assertDatabaseCount('point_transactions', 0);
            }
        }
    }

    public function test_insufficient_balance_does_not_commit_a_debit_or_change_the_balance(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ledger->handle($user, PointTransactionType::Credit, 5, 'test_credit', 'test', 'credit-2', 'r6a:test:credit:2');

        try {
            $ledger->handle($user, PointTransactionType::Debit, 6, 'test_spend', 'test', 'spend-2', 'r6a:test:spend:2');
            $this->fail('A debit larger than the spendable balance must fail.');
        } catch (InsufficientPointBalance) {
            $this->assertSame(5, (int) $user->pointWallet()->value('balance'));
            $this->assertDatabaseCount('point_transactions', 1);
            $this->assertSame(5, (int) DB::table('point_transactions')->sum('amount'));
        }
    }

    public function test_credit_overflow_is_rejected_and_rolls_back_its_ledger_entry(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ledger->handle($user, PointTransactionType::Credit, PHP_INT_MAX, 'test_credit', 'test', 'max', 'r6a:test:credit:max');

        try {
            $ledger->handle($user, PointTransactionType::Credit, 1, 'test_credit', 'test', 'overflow', 'r6a:test:credit:overflow');
            $this->fail('A credit that overflows the signed integer balance must fail.');
        } catch (OverflowException) {
            $this->assertSame(PHP_INT_MAX, (int) $user->pointWallet()->value('balance'));
            $this->assertDatabaseCount('point_transactions', 1);
        }
    }

    public function test_outer_transaction_rollback_reverts_wallet_and_ledger_together(): void
    {
        $user = User::factory()->create();

        try {
            DB::transaction(function () use ($user): void {
                app(ApplyPointTransaction::class)->handle(
                    $user,
                    PointTransactionType::Credit,
                    9,
                    'test_credit',
                    'test',
                    'outer-rollback',
                    'r6a:test:credit:outer',
                );

                throw new LogicException('Rollback the surrounding operation.');
            });
        } catch (LogicException $exception) {
            $this->assertSame('Rollback the surrounding operation.', $exception->getMessage());
        }

        $this->assertDatabaseCount('point_wallets', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_point_transaction_models_are_append_only(): void
    {
        $user = User::factory()->create();
        $transaction = app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            1,
            'test_credit',
            'test',
            'immutable',
            'r6a:test:credit:immutable',
        );

        $this->expectException(LogicException::class);
        $transaction->delete();
    }

    public function test_invalid_ledger_sign_and_negative_wallet_balance_are_rejected_by_database(): void
    {
        $user = User::factory()->create();
        app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            4,
            'test_credit',
            'test',
            'constraint',
            'r6a:test:credit:constraint',
        );
        $wallet = $user->pointWallet()->firstOrFail();

        try {
            DB::table('point_transactions')->insert([
                'wallet_id' => $wallet->getKey(),
                'type' => 'credit',
                'amount' => -1,
                'reason_code' => 'test_credit',
                'reference_type' => 'test',
                'reference_key' => 'bad-sign',
                'idempotency_key' => 'r6a:test:bad-sign',
                'created_at' => now(),
            ]);
            $this->fail('The database must reject a credit with a negative signed amount.');
        } catch (QueryException) {
            $this->assertDatabaseCount('point_transactions', 1);
        }

        try {
            DB::table('point_wallets')->where('id', $wallet->getKey())->update(['balance' => -1]);
            $this->fail('The database must reject a negative wallet balance.');
        } catch (QueryException) {
            $this->assertSame(4, (int) $wallet->fresh()->balance);
        }
    }

    public function test_duplicate_wallet_owner_is_rejected_and_transaction_total_matches_projection(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ledger->handle($user, PointTransactionType::Credit, 8, 'test_credit', 'test', 'one', 'r6a:test:one');

        try {
            DB::table('point_wallets')->insert(['user_id' => $user->getKey(), 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $this->fail('A user may have only one attached wallet.');
        } catch (QueryException) {
            $this->assertSame(
                (int) $user->pointWallet()->value('balance'),
                (int) DB::table('point_transactions')->sum('amount'),
            );
        }

        $this->assertInstanceOf(PointTransaction::class, $user->pointWallet()->firstOrFail()->transactions()->firstOrFail());
    }

    public function test_reusing_a_reference_with_changed_data_is_rejected(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $first = $ledger->handle($user, PointTransactionType::Credit, 6, 'test_credit', 'test', 'stable-reference', 'r6a:test:first-key');

        try {
            $ledger->handle($user, PointTransactionType::Credit, 7, 'test_credit', 'test', 'stable-reference', 'r6a:test:second-key');
            $this->fail('A durable reference must not produce a second changed transaction.');
        } catch (DomainException) {
            $this->assertDatabaseCount('point_transactions', 1);
            $this->assertSame(6, (int) $user->pointWallet()->value('balance'));
            $this->assertSame($first->getKey(), DB::table('point_transactions')->value('id'));
        }
    }

    public function test_unrelated_database_error_propagates_and_rolls_back_wallet_and_ledger(): void
    {
        $user = User::factory()->create();
        PointTransaction::creating(function (PointTransaction $transaction): void {
            if ($transaction->reference_key === 'foreign-key-error') {
                $transaction->wallet_id = 999999999;
            }
        });

        try {
            app(ApplyPointTransaction::class)->handle(
                $user,
                PointTransactionType::Credit,
                5,
                'test_credit',
                'test',
                'foreign-key-error',
                'r6a:test:foreign-key-error',
            );
            $this->fail('Unrelated database errors must not be swallowed as duplicate success.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('point_transactions', $exception->getMessage());
        }

        $this->assertDatabaseCount('point_wallets', 0);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_nested_sqlite_unique_conflict_can_be_recovered_without_changing_balance(): void
    {
        $user = User::factory()->create();
        $original = app(ApplyPointTransaction::class)->handle(
            $user,
            PointTransactionType::Credit,
            10,
            'test_credit',
            'test',
            'nested-conflict',
            'r6a:test:nested-conflict',
        );
        $walletId = $user->pointWallet()->value('id');

        DB::transaction(function () use ($original, $walletId, $user): void {
            try {
                DB::transaction(function () use ($original, $walletId): void {
                    DB::table('point_transactions')->insert([
                        'wallet_id' => $walletId,
                        'type' => 'credit',
                        'amount' => 10,
                        'reason_code' => 'test_credit',
                        'reference_type' => 'test',
                        'reference_key' => 'nested-conflict',
                        'idempotency_key' => 'r6a:test:nested-conflict',
                        'created_at' => $original->created_at,
                    ]);
                }, attempts: 5);
                $this->fail('The unique ledger identity must reject a duplicate insert.');
            } catch (UniqueConstraintViolationException) {
                $this->assertDatabaseCount('point_transactions', 1);
                $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
            }
        });

        $this->assertDatabaseCount('point_transactions', 1);
        $this->assertSame(10, (int) $user->pointWallet()->value('balance'));
    }

    public function test_wallet_projection_is_rebuildable_from_the_signed_ledger(): void
    {
        $user = User::factory()->create();
        $ledger = app(ApplyPointTransaction::class);
        $ledger->handle($user, PointTransactionType::Credit, 14, 'test_credit', 'test', 'rebuild-credit', 'r6a:test:rebuild-credit');
        $ledger->handle($user, PointTransactionType::Debit, 5, 'test_spend', 'test', 'rebuild-debit', 'r6a:test:rebuild-debit');

        $wallet = $user->pointWallet()->firstOrFail();

        $this->assertSame(9, (int) $wallet->balance);
        $this->assertSame(9, $wallet->deriveBalanceFromLedger());
    }

    public function test_wallet_models_cannot_change_or_delete_the_balance_projection(): void
    {
        $user = User::factory()->create();
        app(ApplyPointTransaction::class)->handle($user, PointTransactionType::Credit, 4, 'test_credit', 'test', 'wallet-guard', 'r6a:test:wallet-guard');
        $wallet = $user->pointWallet()->firstOrFail();

        try {
            $wallet->balance = 500;
            $wallet->save();
            $this->fail('Wallet projection changes must use the trusted ledger action.');
        } catch (LogicException) {
            $this->assertSame(4, (int) $wallet->fresh()->balance);
        }

        $this->expectException(LogicException::class);
        $wallet->delete();
    }
}
