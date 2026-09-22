@php
    $user = auth()->user();
@endphp

@if ($user instanceof \App\Models\User && $user->moderationState() === \App\Enums\ModerationState::Restricted)
    <div class="px-3 pt-4 sm:px-8 lg:px-12">
        <aside aria-labelledby="restricted-account-notice-title" class="mx-auto w-full p-3 sm:p-4 2xl:max-w-[1600px]">
            <p id="restricted-account-notice-title" class="text-sm font-semibold sm:text-base">
                {{ __('moderation.restricted.notice_title') }}
            </p>
            <p class="mt-1 break-words text-sm font-semibold leading-relaxed sm:text-base">
                {{ __('moderation.restricted.notice_body') }}
            </p>
        </aside>
    </div>
@endif
