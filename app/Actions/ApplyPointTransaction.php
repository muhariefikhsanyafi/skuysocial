<?php

namespace App\Actions;

use App\Enums\PointTransactionType;
use App\Models\PointTransaction;
use App\Models\PointWallet;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use OverflowException;

class ApplyPointTransaction
{
    /**
     * Apply a trusted internal transaction; reference and idempotency keys must be opaque,
     * server-generated event identifiers, never request values or personal data.
     */
    public function handle(
        User $owner,
        PointTransactionType $type,
        mixed $amount,
        string $reasonCode,
        string $referenceType,
        string $referenceKey,
        string $idempotencyKey,
    ): PointTransaction {
        $ownerId = filter_var($owner->getKey(), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (! $owner->exists || $ownerId === false) {
            throw new InvalidArgumentException('Point transactions require a persisted wallet owner.');
        }

        if (! is_int($amount) || $amount < 1) {
            throw new InvalidArgumentException('Point transaction amounts must be positive whole integers.');
        }

        $this->validateCode($reasonCode, '/\A[a-z][a-z0-9_]{0,63}\z/');
        $this->validateCode($referenceType, '/\A[a-z][a-z0-9_]{0,63}\z/');
        $this->validateCode($referenceKey, '/\A[A-Za-z0-9:_-]{1,128}\z/');
        $this->validateCode($idempotencyKey, '/\A[A-Za-z0-9:_-]{1,128}\z/');

        $signedAmount = $type === PointTransactionType::Credit ? $amount : -$amount;

        try {
            return DB::transaction(function () use (
                $owner,
                $ownerId,
                $type,
                $amount,
                $signedAmount,
                $reasonCode,
                $referenceType,
                $referenceKey,
                $idempotencyKey,
            ): PointTransaction {
                $wallet = $this->resolveWallet($ownerId);
                $wallet = PointWallet::query()
                    ->whereKey($wallet->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingByKey = $wallet->transactions()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                $existingByReference = $wallet->transactions()
                    ->where('reference_type', $referenceType)
                    ->where('reference_key', $referenceKey)
                    ->first();

                if ($existingByKey !== null || $existingByReference !== null) {
                    return $this->resolveExisting(
                        $existingByKey,
                        $existingByReference,
                        $type,
                        $signedAmount,
                        $reasonCode,
                        $referenceType,
                        $referenceKey,
                    );
                }

                $now = now();
                $transaction = new PointTransaction;
                // Let identity constraints fail before the cached balance changes.
                $transaction->forceFill([
                    'wallet_id' => $wallet->getKey(),
                    'type' => $type,
                    'amount' => $signedAmount,
                    'reason_code' => $reasonCode,
                    'reference_type' => $referenceType,
                    'reference_key' => $referenceKey,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => $now,
                ])->save();

                $balanceUpdate = DB::table('point_wallets')->where('id', $wallet->getKey());

                if ($type === PointTransactionType::Credit) {
                    $updated = $balanceUpdate
                        ->where('balance', '>=', 0)
                        ->where('balance', '<=', PHP_INT_MAX - $amount)
                        ->increment('balance', $amount, ['updated_at' => $now]);
                } else {
                    $updated = $balanceUpdate
                        ->where('balance', '>=', $amount)
                        ->decrement('balance', $amount, ['updated_at' => $now]);
                }

                if ($updated !== 1) {
                    $currentBalance = DB::table('point_wallets')
                        ->where('id', $wallet->getKey())
                        ->value('balance');

                    if ($currentBalance === null || (int) $currentBalance < 0) {
                        throw new LogicException('The point wallet balance is missing or invalid.');
                    }

                    if (
                        $type === PointTransactionType::Credit
                        && (int) $currentBalance > PHP_INT_MAX - $amount
                    ) {
                        throw new OverflowException('The point wallet balance cannot be increased without overflow.');
                    }

                    if (
                        $type === PointTransactionType::Debit
                        && (int) $currentBalance < $amount
                    ) {
                        throw new InsufficientPointBalance('The point wallet has insufficient spendable Points.');
                    }

                    throw new LogicException('The point wallet balance could not be updated.');
                }

                return $transaction;
            }, attempts: 5);
        } catch (UniqueConstraintViolationException $exception) {
            $wallet = PointWallet::query()
                ->where('user_id', $ownerId)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                throw $exception;
            }

            $existingByKey = $wallet->transactions()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            $existingByReference = $wallet->transactions()
                ->where('reference_type', $referenceType)
                ->where('reference_key', $referenceKey)
                ->lockForUpdate()
                ->first();

            if ($existingByKey === null && $existingByReference === null) {
                throw $exception;
            }

            return $this->resolveExisting(
                $existingByKey,
                $existingByReference,
                $type,
                $signedAmount,
                $reasonCode,
                $referenceType,
                $referenceKey,
            );
        }
    }

    private function resolveWallet(int $ownerId): PointWallet
    {
        $wallet = PointWallet::query()
            ->where('user_id', $ownerId)
            ->lockForUpdate()
            ->first();

        if ($wallet !== null) {
            return $wallet;
        }

        try {
            return PointWallet::query()->forceCreate([
                'user_id' => $ownerId,
                'balance' => 0,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $wallet = PointWallet::query()
                ->where('user_id', $ownerId)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                throw $exception;
            }

            return $wallet;
        }
    }

    private function validateCode(string $value, string $pattern): void
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException('Point transaction identifiers must use bounded non-personal tokens.');
        }
    }

    private function resolveExisting(
        ?PointTransaction $existingByKey,
        ?PointTransaction $existingByReference,
        PointTransactionType $type,
        int $signedAmount,
        string $reasonCode,
        string $referenceType,
        string $referenceKey,
    ): PointTransaction {
        if (
            $existingByKey !== null
            && $existingByReference !== null
            && ! $existingByKey->is($existingByReference)
        ) {
            throw new DomainException('The point transaction identities refer to different ledger entries.');
        }

        $existing = $existingByKey ?? $existingByReference;

        if (! $existing instanceof PointTransaction) {
            throw new LogicException('The conflicting point transaction could not be resolved.');
        }

        if (
            $existing->type !== $type
            || (int) $existing->amount !== $signedAmount
            || $existing->reason_code !== $reasonCode
            || $existing->reference_type !== $referenceType
            || $existing->reference_key !== $referenceKey
        ) {
            throw new DomainException('The point transaction identity was reused with different immutable data.');
        }

        return $existing;
    }
}
