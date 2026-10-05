<?php

namespace App\Models;

use App\Enums\AppreciationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAppreciation extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'type' => AppreciationType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
