<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ModerationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'target_user_id',
        'actor_user_id',
        'previous_state',
        'new_state',
        'reason_code',
        'reason_text',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Moderation events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Moderation events are append-only.');
        });
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
