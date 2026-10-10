<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PostPollController extends Controller
{
    public function vote(Request $request, Post $post)
    {
        $poll = $post->poll()->firstOrFail();
        $validated = $request->validate([
            'option_id' => [
                'required',
                'integer',
                Rule::exists('post_poll_options', 'id')->where(
                    static fn ($query) => $query->where('poll_id', $poll->getKey()),
                ),
            ],
        ]);
        $viewerId = (int) $request->user()->getKey();
        $optionId = (int) $validated['option_id'];
        $existingVote = $poll->votes()->where('user_id', $viewerId)->first();

        if ($existingVote !== null) {
            if ((int) $existingVote->option_id !== $optionId) {
                return back()->withErrors(['option_id' => __('polls.already_voted')]);
            }

            return back();
        }

        try {
            DB::transaction(static function () use ($poll, $viewerId, $optionId): void {
                $poll->votes()->create([
                    'option_id' => $optionId,
                    'user_id' => $viewerId,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $existingVote = $poll->votes()->where('user_id', $viewerId)->first();
            if ($existingVote === null || (int) $existingVote->option_id !== $optionId) {
                return back()->withErrors(['option_id' => __('polls.already_voted')]);
            }
        }

        return back()->with('status', __('polls.vote_recorded'));
    }
}
