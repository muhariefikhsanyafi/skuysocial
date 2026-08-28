<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = ['post_id', 'user_id', 'content', 'parent_id'];
	
	/**
     * Relasi untuk mengambil semua balasan dari komentar ini
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Relasi ke komentar induknya (opsional, tapi berguna)
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    // Komentar ini milik siapa?
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Komentar ini ada di postingan mana?
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
	
	// Relasi untuk mengambil data Like pada komentar
    public function likes()
    {
        return $this->belongsToMany(User::class, 'comment_likes');
    }

    // Mengecek apakah komentar ini sudah disukai oleh user tertentu
    public function isLikedBy(User $user)
    {
        return $this->likes->contains('id', $user->id);
    }
}