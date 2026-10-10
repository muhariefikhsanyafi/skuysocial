<?php

namespace App\Http\Controllers;

use App\Enums\ModerationState;
use App\Enums\UserRole;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ProfileController extends Controller
{
    /**
     * Display the user's account settings.
     */
    public function edit(Request $request, string $username): View
    {
        $user = $request->user();
        abort_unless($username === $user->username, 404);

        $user->loadMissing('pointWallet');

        return view('profile.edit', [
            'user' => $user,
            'pointBalance' => (int) ($user->pointWallet?->balance ?? 0),
        ]);
    }

    /**
     * Update the user's account email.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if (! $user->save()) {
            throw new RuntimeException('Failed to save account email.');
        }

        return Redirect::route('profile.edit', ['username' => $user->username])
            ->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'string', 'current_password'],
            'username_confirmation' => [
                'required',
                'string',
                'max:255',
                Rule::in([$user->username]),
            ],
        ], [
            'username_confirmation.in' => __('settings.delete.username_mismatch'),
        ], [
            'password' => __('settings.delete.password'),
            'username_confirmation' => __('settings.delete.username_confirmation'),
        ]);

        $deleted = DB::transaction(function () use ($user): bool {
            if ($user->isUsableAdministrator()) {
                $usableAdministrators = User::query()
                    ->where('role', UserRole::Administrator->value)
                    ->whereNotNull('email_verified_at')
                    ->where('moderation_state', ModerationState::Active->value)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                if ($usableAdministrators->count() <= 1) {
                    return false;
                }
            }

            DB::table('post_poll_votes')->where('user_id', $user->getKey())->delete();

            if (! $user->delete()) {
                throw new RuntimeException('Failed to delete account.');
            }

            return true;
        });

        if (! $deleted) {
            return Redirect::back()->withErrors([
                'username_confirmation' => __('settings.delete.only_usable_administrator'),
            ], 'userDeletion');
        }

        Auth::logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
