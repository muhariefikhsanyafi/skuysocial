<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostPollOption extends Model
{
    protected $fillable = ['poll_id', 'label', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(PostPoll::class, 'poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PostPollVote::class, 'option_id');
    }
}
