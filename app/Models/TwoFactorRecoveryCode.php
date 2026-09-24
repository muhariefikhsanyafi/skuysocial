<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorRecoveryCode extends Model
{
    protected $table = 'user_two_factor_recovery_codes';

    protected $hidden = ['code_hash'];

    protected $fillable = ['user_id', 'code_hash'];

    public function twoFactorAuthentication(): BelongsTo
    {
        return $this->belongsTo(TwoFactorAuthentication::class, 'user_id', 'user_id');
    }
}
