@php
    $user = auth()->user();

    if (request()->is('admin', 'admin/*') && $user instanceof \App\Models\User) {
        $locale = app(\App\Services\LocalePreference::class)->supported($user->locale);

        if ($locale !== null) {
            app()->setLocale($locale);
        }
    }

    $isRestrictedAdministrator = request()->is('admin', 'admin/*')
        && $user instanceof \App\Models\User
        && \App\Enums\UserRole::tryFrom((string) $user->role) === \App\Enums\UserRole::Administrator
        && $user->hasVerifiedEmail();
    $isRestrictedDenial = $user instanceof \App\Models\User
        && $user->moderationState() === \App\Enums\ModerationState::Restricted
        && (
            $exception instanceof \App\Exceptions\RestrictedAccountActionException
            || $isRestrictedAdministrator
        );
    $homeUrl = $user instanceof \App\Models\User ? route('feed') : url('/');
@endphp

@include('errors._page', [
    'statusCode' => 403,
    'title' => __('moderation.forbidden.title'),
    'contextTitle' => $isRestrictedDenial ? __('moderation.restricted.notice_title') : null,
    'body' => $isRestrictedDenial
        ? __('moderation.restricted.denial_body')
        : __('moderation.forbidden.generic_body'),
    'primaryUrl' => $homeUrl,
    'primaryLabel' => __('moderation.forbidden.back_to_home'),
])
