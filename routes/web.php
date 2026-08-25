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
    
	// Rute Fitur Edit & Hapus Postingan
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

	// RUTE PROFIL PENGGUNA (Diletakkan di paling bawah karena menggunakan wildcard satu segmen)
    Route::get('/{user:username}', [UserController::class, 'show'])->name('user.profile');
});

