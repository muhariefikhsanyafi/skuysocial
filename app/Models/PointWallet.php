<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PointWallet extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Point wallet changes must use the trusted ledger action.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Point wallets are retained with their transaction history.');
        });
    }

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'wallet_id');
    }

    /**
     * Reconstruct the authoritative total for integrity checks; routine reads use the projection.
     */
    public function deriveBalanceFromLedger(): int
    {
        return (int) $this->transactions()->sum('amount');
    }
}
