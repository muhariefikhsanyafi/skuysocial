@php
    $user = auth()->user();
@endphp

@if ($user instanceof \App\Models\User && $user->moderationState() === \App\Enums\ModerationState::Restricted)
    <div class="px-3 pt-4 sm:px-8 lg:px-12">
        <aside aria-labelledby="restricted-account-notice-title" class="mx-auto w-full border-2 border-black bg-yellow-300 p-3 text-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:border-4 sm:p-4 2xl:max-w-[1600px]">
            <p id="restricted-account-notice-title" class="text-sm font-black sm:text-base">
                {{ __('moderation.restricted.notice_title') }}
            </p>
            <p class="mt-1 break-words text-sm font-semibold leading-relaxed sm:text-base">
                {{ __('moderation.restricted.notice_body') }}
            </p>
        </aside>
    </div>
@endif
