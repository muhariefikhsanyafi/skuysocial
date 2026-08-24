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
    protected $fillable = ['content', 'image', 'user_id', 'topic'];

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
}