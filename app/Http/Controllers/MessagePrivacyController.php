<?php

namespace App\Http\Controllers;

use App\Enums\MessagePrivacy;
use App\Http\Requests\UpdateMessagePrivacyRequest;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class MessagePrivacyController extends Controller
{
    public function update(UpdateMessagePrivacyRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->message_privacy = MessagePrivacy::from($request->validated('message_privacy'));

        if (! $user->save()) {
            throw new RuntimeException('Failed to update message privacy.');
        }

        return redirect()
            ->route('profile.edit', $user->username)
            ->with('status', 'message-privacy-updated');
    }
}
