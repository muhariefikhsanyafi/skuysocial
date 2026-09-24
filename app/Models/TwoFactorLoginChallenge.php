<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorLoginChallenge extends Model
{
    protected $table = 'user_two_factor_login_challenges';

    protected $fillable = [
        'user_id',
        'challenge_digest',
        'session_digest',
        'auth_session_version',
        'remember',
        'intended_url',
        'source',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'challenge_digest',
        'session_digest',
    ];

    protected function casts(): array
    {
        return [
            'remember' => 'boolean',
            'auth_session_version' => 'integer',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
