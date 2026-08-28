<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    // Mengizinkan kolom 'content' agar bisa diisi form input nanti
    protected $fillable = ['content', 'image', 'images', 'user_id', 'repost_id', 'topic'];
	
	protected $casts = [
    'images' => 'array',
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