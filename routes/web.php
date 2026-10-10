<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DailyCheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HashtagController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MessagePrivacyController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\PostAppreciationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostPollController;
use App\Http\Controllers\PointWalletController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/pengumuman', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/pengumuman/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::get('/@{username}/pengaturan-akun', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/poin/riwayat', [PointWalletController::class, 'index'])->name('points.history');
    Route::post('/daily-check-in', [DailyCheckInController::class, 'store'])
        ->middleware('moderation.active')
        ->name('daily-checkin.store');
    Route::patch('/pengaturan/messaging-privacy', [MessagePrivacyController::class, 'update'])
        ->name('settings.messaging-privacy.update');
    Route::get('/profile', static function (Request $request) {
        return redirect()->route('profile.edit', ['username' => $request->user()->username] + $request->query());
    })->name('profile.legacy-edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('moderation.active')
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('moderation.active')
        ->name('profile.destroy');

    // Rute untuk Pencarian
    Route::get('/cari', [SearchController::class, 'index'])->name('search');
    Route::get('/pesan', [MessagesController::class, 'index'])->name('messages.index');
    Route::post('/pesan/mulai/{user:username}', [MessagesController::class, 'start'])
        ->middleware('moderation.active')
        ->name('messages.start');
    Route::post('/pesan/{conversation}', [MessagesController::class, 'store'])
        ->middleware('moderation.active')
        ->name('messages.store');
    Route::get('/pesan/{conversation}', [MessagesController::class, 'show'])->name('messages.show');
    Route::post('/pesan/{conversation}/blokir', [MessagesController::class, 'block'])->name('messages.block');
    Route::delete('/pesan/{conversation}/blokir', [MessagesController::class, 'unblock'])->name('messages.unblock');

    // Rute untuk aksi Follow/Unfollow
    Route::post('/user/{user}/follow', [UserController::class, 'toggleFollow'])
        ->middleware('moderation.active')
        ->name('user.follow');

    Route::get('/aktivitas', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('/user/{user}/followers', [UserController::class, 'followers'])->name('user.followers');
    Route::get('/user/{user}/following', [UserController::class, 'following'])->name('user.following');

    // Rute untuk Halaman Profil
    Route::get('/user/{username}', static function (Request $request, string $username) {
        return redirect()->route('user.profile', ['username' => $username] + $request->query());
    })->name('user.profile.legacy');
});

Route::get('/@{username}', [UserController::class, 'show'])->name('user.profile');

require __DIR__.'/auth.php';

Route::get('/post/{post}', [PostController::class, 'show'])
    ->name('posts.show')
    ->middleware(['auth', 'verified', 'inertia']);

// Rute ini hanya bisa diakses kalau user sudah login (auth)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/feed', [PostController::class, 'index'])->name('feed');
    Route::post('/feed', [PostController::class, 'store'])
        ->middleware(['moderation.active', 'throttle:5,1'])
        ->name('posts.store');

    // Rute untuk Topik
    Route::get('/topik/{topic}', [PostController::class, 'topic'])->name('topik.show')->middleware('auth');

    // Rute Hashtag
    Route::get('/tags/{name}', [HashtagController::class, 'show'])->name('tags.show');

    // Rute Fitur Like & Komentar
    Route::post('/posts/{post}/appreciation', [PostAppreciationController::class, 'store'])
        ->middleware('moderation.active')
        ->name('posts.appreciations.store');
    Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])
        ->middleware('moderation.active')
        ->name('posts.like');
    Route::get('/tersimpan', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/posts/{post}/bookmark', [BookmarkController::class, 'store'])
        ->middleware('moderation.active')
        ->name('bookmarks.store');
    Route::delete('/posts/{post}/bookmark', [BookmarkController::class, 'destroy'])
        ->middleware('moderation.active')
        ->name('bookmarks.destroy');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])
        ->middleware(['moderation.active', 'throttle:10,1'])
        ->name('comments.store');
    Route::post('/comments/{comment}/like', [CommentController::class, 'like'])
        ->middleware('moderation.active')
        ->name('comments.like');

    // Rute Repost Postingan
    Route::post('/post/{post}/repost', [PostController::class, 'repost'])
        ->middleware('moderation.active')
        ->name('posts.repost');
    Route::post('/posts/{post}/poll-votes', [PostPollController::class, 'vote'])
        ->middleware(['moderation.active', 'throttle:10,1'])
        ->name('posts.poll-votes');
    Route::post('/posts/{post}/pin', [PostController::class, 'togglePin'])
        ->middleware('moderation.active')
        ->name('posts.pin');

    // Rute Fitur Edit & Hapus Postingan
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])
        ->middleware('moderation.active')
        ->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->middleware('moderation.active')
        ->name('posts.destroy');

    // Rute Pengaturan/Edit Profil
    Route::get('/@{username}/pengaturan-profil', [UserController::class, 'edit'])->name('user.edit');
    Route::get('/pengaturan/profil', static function (Request $request) {
        return redirect()->route('user.edit', ['username' => $request->user()->username] + $request->query());
    })->name('user.edit.legacy');
    Route::post('/pengaturan/profil', [UserController::class, 'update'])
        ->middleware('moderation.active')
        ->name('user.update');

    Route::get('/pengaturan/keamanan/2fa', [TwoFactorAuthenticationController::class, 'show'])
        ->middleware('password.confirm.recent')
        ->name('security.two-factor.show');
    Route::post('/pengaturan/keamanan/2fa/setup', [TwoFactorAuthenticationController::class, 'startSetup'])
        ->middleware(['password.confirm.recent', 'throttle:two-factor-security'])
        ->name('security.two-factor.setup');
    Route::post('/pengaturan/keamanan/2fa/confirm', [TwoFactorAuthenticationController::class, 'confirmSetup'])
        ->middleware(['password.confirm.recent', 'throttle:two-factor-security'])
        ->name('security.two-factor.confirm');
    Route::get('/pengaturan/keamanan/2fa/recovery-codes', [TwoFactorAuthenticationController::class, 'showRecoveryCodes'])
        ->middleware('password.confirm.recent')
        ->name('security.two-factor.recovery-codes.display');
    Route::post('/pengaturan/keamanan/2fa/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])
        ->middleware(['password.confirm.recent', 'throttle:two-factor-security'])
        ->name('security.two-factor.recovery-codes.regenerate');
    Route::delete('/pengaturan/keamanan/2fa', [TwoFactorAuthenticationController::class, 'disable'])
        ->middleware(['password.confirm.recent', 'throttle:two-factor-security'])
        ->name('security.two-factor.disable');
});

Route::fallback(static function (Request $request): never {
    $allowedMethods = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if ($route->isFallback || ! $route->matches($request, false)) {
            continue;
        }

        $allowedMethods = array_merge($allowedMethods, $route->methods());
    }

    $allowedMethods = array_values(array_unique($allowedMethods));

    if ($allowedMethods !== []) {
        throw new MethodNotAllowedHttpException($allowedMethods);
    }

    abort(404);
})->where('fallbackPlaceholder', '^(?!api(?:/|$)).*');
