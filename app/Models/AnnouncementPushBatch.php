<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementPushBatch extends Model
{
    protected $fillable = [
        'announcement_id', 'batch_key', 'batch_type', 'locale', 'range_start_user_id',
        'range_end_user_id', 'recipient_count', 'status', 'provider_outcome',
        'error_class', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
