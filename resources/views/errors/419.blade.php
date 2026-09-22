@php
    $user = auth()->user();
    $homeUrl = $user instanceof \App\Models\User ? route('feed') : url('/');
    $loginUrl = Route::has('login') ? route('login') : null;
@endphp

@include('errors._page', [
    'statusCode' => 419,
    'title' => __('errors.419.title'),
    'body' => __('errors.419.body'),
    'primaryUrl' => $homeUrl,
    'primaryLabel' => __('errors.419.action'),
    'secondaryUrl' => $user instanceof \App\Models\User ? null : $loginUrl,
    'secondaryLabel' => $user instanceof \App\Models\User ? null : __('errors.419.secondary_action'),
])
