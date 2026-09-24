<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['operation_digest', 'session_digest', 'encrypted_payload'])]
class TwoFactorRecoveryCodeHandoff extends Model
{
    protected $table = 'user_two_factor_recovery_code_handoffs';

    protected $fillable = [
        'user_id',
        'operation_digest',
        'session_digest',
        'purpose',
        'encrypted_payload',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
