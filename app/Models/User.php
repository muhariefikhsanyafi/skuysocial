<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Services\LocalePreference;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'avatar', 'cover', 'bio'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's supported locale preference for framework notifications.
     */
    public function preferredLocale()
    {
        $locales = app(LocalePreference::class);

        return $locales->supported($this->locale)
            ?? $locales->currentLocale();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && UserRole::tryFrom((string) $this->role) === UserRole::Administrator
            && $this->hasVerifiedEmail();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Menggunakan kolom username sebagai route key default untuk URL profil
     */
    public function getRouteKeyName(): string
    {
        return 'username';
    }

    // 1. Mengambil data orang-orang yang mengikuti user ini (Followers)
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    // 2. Mengambil data orang-orang yang diikuti oleh user ini (Followings)
    public function followings()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    // 3. Fungsi cerdas untuk mengecek apakah user yang sedang login mengikuti user lain
    public function isFollowing(User $user)
    {
        return $this->followings()->where('following_id', $user->id)->exists();
    }
}
