<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Announcement extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_TARGETED = 'targeted';

    protected $fillable = [
        'title_id', 'title_en', 'body_id', 'body_en', 'audience_type', 'status',
        'audience_cutoff_user_id', 'created_by', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (self $announcement): void {
            if ($announcement->exists && $announcement->getOriginal('status') === self::STATUS_SENT && $announcement->isDirty()) {
                throw new LogicException('Sent announcements are immutable.');
            }
        });

        static::deleting(static function (self $announcement): void {
            if ($announcement->status === self::STATUS_SENT) {
                throw new LogicException('Sent announcements cannot be deleted.');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function pushBatches(): HasMany
    {
        return $this->hasMany(AnnouncementPushBatch::class);
    }
}
