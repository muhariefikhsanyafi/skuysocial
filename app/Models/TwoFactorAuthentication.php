<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Hidden(['secret', 'pending_secret'])]
class TwoFactorAuthentication extends Model
{
    protected $table = 'user_two_factor_authentications';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'pending_secret' => 'encrypted',
            'enabled_at' => 'immutable_datetime',
            'pending_expires_at' => 'immutable_datetime',
            'last_used_step' => 'integer',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled_at !== null && is_string($this->secret) && $this->secret !== '';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class, 'user_id', 'user_id');
    }
}
