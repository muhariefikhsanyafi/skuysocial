<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\HashtagController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('moderation.active')
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('moderation.active')
        ->name('profile.destroy');

    // Rute untuk Pencarian
    Route::get('/cari', [SearchController::class, 'index'])->name('search');

    // Rute untuk aksi Follow/Unfollow
    Route::post('/user/{user}/follow', [UserController::class, 'toggleFollow'])
        ->middleware('moderation.active')
        ->name('user.follow');

    Route::get('/aktivitas', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('/user/{user}/followers', [UserController::class, 'followers'])->name('user.followers');
    Route::get('/user/{user}/following', [UserController::class, 'following'])->name('user.following');

    // Rute untuk Halaman Profil
    Route::get('/user/{username}', [UserController::class, 'show'])->name('user.profile');
});

require __DIR__.'/auth.php';

Route::get('/post/{post}', [PostController::class, 'show'])
    ->name('posts.show')
    ->middleware(['auth', 'inertia']);

// Rute ini hanya bisa diakses kalau user sudah login (auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/feed', [PostController::class, 'index'])->name('feed');
    Route::post('/feed', [PostController::class, 'store'])
        ->middleware(['moderation.active', 'throttle:5,1'])
        ->name('posts.store');

    // Rute untuk Topik
    Route::get('/topik/{topic}', [PostController::class, 'topic'])->name('topik.show')->middleware('auth');

    // Rute Hashtag
    Route::get('/tags/{name}', [HashtagController::class, 'show'])->name('tags.show');

    // Rute Fitur Like & Komentar
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

    // Rute Fitur Edit & Hapus Postingan
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])
        ->middleware('moderation.active')
        ->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->middleware('moderation.active')
        ->name('posts.destroy');

    // Rute Pengaturan/Edit Profil
    Route::get('/pengaturan/profil', [UserController::class, 'edit'])->name('user.edit');
    Route::post('/pengaturan/profil', [UserController::class, 'update'])
        ->middleware('moderation.active')
        ->name('user.update');
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
