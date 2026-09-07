<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\LocaleController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

	// Rute untuk Pencarian
	Route::get('/cari', [SearchController::class, 'index'])->name('search');

	// Rute untuk aksi Follow/Unfollow
	Route::post('/user/{user}/follow', [App\Http\Controllers\UserController::class, 'toggleFollow'])->name('user.follow');

	Route::get('/aktivitas', [\App\Http\Controllers\ActivityController::class, 'index'])->name('activity.index');

	Route::get('/user/{user}/followers', [\App\Http\Controllers\UserController::class, 'followers'])->name('user.followers');
	Route::get('/user/{user}/following', [\App\Http\Controllers\UserController::class, 'following'])->name('user.following');

	// Rute untuk Halaman Profil
	Route::get('/user/{username}', [App\Http\Controllers\UserController::class, 'show'])->name('user.profile');
});

require __DIR__.'/auth.php';

use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\HashtagController;

	Route::get('/post/{post}', [App\Http\Controllers\PostController::class, 'show'])->name('posts.show')->middleware('auth');

	// Rute ini hanya bisa diakses kalau user sudah login (auth)
	Route::middleware(['auth'])->group(function () {
    Route::get('/feed', [PostController::class, 'index'])->name('feed');
    Route::post('/feed', [PostController::class, 'store'])->name('posts.store')->middleware('throttle:5,1');
    
	// Rute untuk Topik
	Route::get('/topik/{topic}', [\App\Http\Controllers\PostController::class, 'topic'])->name('topik.show')->middleware('auth');
    
	// Rute Hashtag
	Route::get('/tags/{name}', [HashtagController::class, 'show'])->name('tags.show');
    
	// Rute Fitur Like & Komentar
    Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])->name('posts.like');
    Route::get('/tersimpan', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/posts/{post}/bookmark', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::delete('/posts/{post}/bookmark', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store')->middleware('throttle:10,1');
    Route::post('/comments/{comment}/like', [App\Http\Controllers\CommentController::class, 'like'])->name('comments.like');
	
	# Rute Repost Postingan
	Route::post('/post/{post}/repost', [App\Http\Controllers\PostController::class, 'repost'])->name('posts.repost');
	
	// Rute Fitur Edit & Hapus Postingan
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
	
	// Rute Pengaturan/Edit Profil
    Route::get('/pengaturan/profil', [App\Http\Controllers\UserController::class, 'edit'])->name('user.edit');
    Route::post('/pengaturan/profil', [App\Http\Controllers\UserController::class, 'update'])->name('user.update');
});
