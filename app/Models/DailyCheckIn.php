<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCheckIn extends Model
{
    protected $table = 'daily_checkins';

    protected $guarded = ['*'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
