<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
		// 1. Validasi input termasuk username dengan regex anti-spasi & izinkan titik (.)
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 
                'string', 
                'max:255', 
                'regex:/^[a-zA-Z0-9_.]+$/', 
                'unique:users,username,' . $request->user()->id
            ],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
        ]);

        // 2. Menyimpan data name, username, dan email yang sudah divalidasi
        $request->user()->fill($request->only(['name', 'username', 'email']));
		
        // Menyimpan data name, email, dan bio
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        // Logika Khusus untuk Upload Avatar
        if ($request->hasFile('avatar')) {
            // Jika user sebelumnya sudah punya foto, hapus foto lama agar server tidak penuh
            if ($request->user()->avatar) {
                Storage::disk('public')->delete($request->user()->avatar);
            }
            
            // Simpan foto baru ke folder 'avatars' di storage/app/public
            $path = $request->file('avatar')->store('avatars', 'public');
            
            // Masukkan path foto tersebut ke database
            $request->user()->avatar = $path;
        }
		
		if ($request->hasFile('cover')) {
            if ($request->user()->cover) {
                Storage::disk('public')->delete($request->user()->cover);
            }
            
            $coverPath = $request->file('cover')->store('covers', 'public');
            $request->user()->cover = $coverPath;
        }

        // Simpan semua perubahan ke database
        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
