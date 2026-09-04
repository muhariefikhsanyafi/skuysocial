<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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
        $user = $request->user();
        $oldAvatar = $user->avatar;
        $oldCover = $user->cover;

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

        $disk = Storage::disk('public');
        $newAvatar = null;
        $newCover = null;
        $newPaths = [];

        try {
            // Logika Khusus untuk Upload Avatar
            if ($request->hasFile('avatar')) {
                // Simpan foto baru sebelum referensi lama dihapus
                $newAvatar = $request->file('avatar')->store('avatars', 'public');
                if (! is_string($newAvatar)) {
                    throw new RuntimeException('Failed to store the profile avatar.');
                }

                $newPaths[] = $newAvatar;
                $user->avatar = $newAvatar;
            }

            if ($request->hasFile('cover')) {
                $newCover = $request->file('cover')->store('covers', 'public');
                if (! is_string($newCover)) {
                    throw new RuntimeException('Failed to store the profile cover.');
                }

                $newPaths[] = $newCover;
                $user->cover = $newCover;
            }

            // Simpan semua perubahan ke database dalam transaksi singkat
            DB::transaction(function () use ($user): void {
                if (! $user->save()) {
                    throw new RuntimeException('Failed to save profile changes.');
                }
            });
        } catch (Throwable $exception) {
            $disk->delete($newPaths);

            throw $exception;
        }

        $obsoletePaths = array_filter([
            $newAvatar !== null && $newAvatar !== $oldAvatar ? $oldAvatar : null,
            $newCover !== null && $newCover !== $oldCover ? $oldCover : null,
        ]);
        $disk->delete(array_values(array_unique($obsoletePaths)));

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
