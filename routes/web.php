<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LikeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
	
	// Rute untuk aksi Follow/Unfollow
Route::post('/user/{user}/follow', [App\Http\Controllers\UserController::class, 'toggleFollow'])->name('user.follow');
// Rute untuk Halaman Profil
Route::get('/user/{username}', [App\Http\Controllers\UserController::class, 'show'])->name('user.profile');
});

require __DIR__.'/auth.php';

use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\HashtagController;

Route::get('/jalankan-migrasi', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return 'Mantap! Migrasi Database Berhasil! Silakan buka halaman utama.';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::get('/perbaiki-username-lama', function () {
    \App\Models\User::whereNull('username')->get()->each(function($user) {
        $user->username = strtolower(str_replace(' ', '', $user->name)) . $user->id;
        $user->save();
    });
    return 'Berhasil memperbaiki username akun lama!';
});

	Route::get('/post/{post}', [App\Http\Controllers\PostController::class, 'show'])->name('posts.show');

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

