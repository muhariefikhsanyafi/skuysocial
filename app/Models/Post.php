<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Post extends Model
{
    use HasFactory;

    // Mengizinkan kolom 'content' agar bisa diisi form input nanti
    protected $fillable = ['content', 'image', 'images', 'user_id', 'repost_id', 'topic'];
	
	protected $casts = [
    'images' => 'array',
    'pinned_at' => 'datetime',
];

    // Relasi: Setiap satu postingan itu pasti dimiliki oleh satu user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
	
	// Menghubungkan postingan dengan data like
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function appreciations(): HasMany
    {
        return $this->hasMany(PostAppreciation::class);
    }

    public function poll(): HasOne
    {
        return $this->hasOne(PostPoll::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    // Fungsi untuk mengecek apakah user tertentu sudah menyukai status ini
    public function isLikedBy(User $user): bool
    {
        return $this->likes->contains('user_id', $user->id);
    }
	
	// Relasi: Satu postingan bisa memiliki banyak komentar
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Bounded root comments used by the feed preview.
     */
    public function feedComments(): HasMany
    {
        return $this->comments()
            ->whereNull('parent_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(3);
    }

    /**
     * Root comments used by the post detail paginator.
     */
    public function detailComments(): HasMany
    {
        return $this->comments()
            ->whereNull('parent_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function scopeWhereHasMedia(Builder $query): Builder
    {
        return $query->where(static function (Builder $query): void {
            $query->where(static function (Builder $query): void {
                $query->whereNotNull('image')->where('image', '<>', '');
            })->orWhereJsonLength('images', '>', 0);
        });
    }

    public function scopeWithAppreciationState(Builder $query, int $viewerId): Builder
    {
        return $query
            ->withCount([
                'appreciations',
                'appreciations as appreciation_bermanfaat_count' => static fn (Builder $query) => $query->where('type', 'bermanfaat'),
                'appreciations as appreciation_inspiratif_count' => static fn (Builder $query) => $query->where('type', 'inspiratif'),
                'appreciations as appreciation_solutif_count' => static fn (Builder $query) => $query->where('type', 'solutif'),
            ])
            ->withSum('appreciations', 'points_spent')
            ->with(['appreciations' => static fn ($query) => $query
                ->where('user_id', $viewerId)
                ->select(['id', 'post_id', 'type'])]);
    }

    public function scopeWithPollPresentation(Builder $query, ?int $viewerId): Builder
    {
        return $query->with([
            'poll.options' => static fn ($options) => $options->withCount('votes'),
            'poll.viewerVotes' => static function ($votes) use ($viewerId): void {
                if ($viewerId === null) {
                    $votes->whereRaw('1 = 0');
                    return;
                }

                $votes->where('user_id', $viewerId);
            },
        ]);
    }

    /**
     * Load the data used by post listings that render comment previews.
     */
    public function scopeWithInteractiveListing(Builder $query, int $viewerId): Builder
    {
        $interactionCounts = [
            'likes',
            'comments',
            'comments as root_comments_count' => fn (Builder $query) => $query->whereNull('parent_id'),
            'reposts',
        ];
        $viewerInteractionState = [
            'likes as liked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
            'reposts as reposted_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
            'bookmarks as bookmarked_by_me' => fn (Builder $query) => $query->where('user_id', $viewerId),
        ];
        $loadInteractionState = static fn ($query) => $query
            ->withAppreciationState($viewerId)
            ->withCount($interactionCounts)
            ->withExists($viewerInteractionState);
        $loadDisplayPost = static fn ($query) => $loadInteractionState($query)
            ->withPollPresentation($viewerId)
            ->with([
                'user',
                'feedComments.user',
                'feedComments.feedReplies.user',
            ]);

        return $loadInteractionState($query)
            ->with([
                'user',
                'originalPost' => $loadDisplayPost,
                'feedComments.user',
                'feedComments.feedReplies.user',
            ])
            ->withPollPresentation($viewerId);
    }
	
	// Relasi ke tabel hashtags
    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class);
    }
	
	// Beritahu Laravel agar menggunakan kolom 'uid' di URL, bukan 'id'
    public function getRouteKeyName()
    {
        return 'uid';
    }

    /**
     * Backward-compatible alias for notification payloads created before uid
     * became the public route key.
     */
    public function getSlugAttribute(): ?string
    {
        return $this->uid;
    }

    // Buat kode acak 11 karakter secara otomatis setiap kali utas baru diposting
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($post) {
            if (empty($post->uid)) {
                $post->uid = \Illuminate\Support\Str::random(11);
            }
        });
    }
	
	// Relasi ke postingan asli yang di-repost
    public function originalPost()
    {
        return $this->belongsTo(Post::class, 'repost_id');
    }

    public function interactionTarget(): self
    {
        return $this->originalPost ?? $this;
    }

    // Menghitung berapa kali postingan ini di-repost orang lain
    public function reposts()
    {
        return $this->hasMany(Post::class, 'repost_id');
    }

    // Mengecek apakah user tertentu sudah merepost postingan ini
    public function isRepostedBy($user)
    {
        if (!$user) return false;
        return $this->reposts()->where('user_id', $user->id)->exists();
    }
}
