@php
    $user = auth()->user();
    $homeUrl = $user instanceof \App\Models\User ? route('feed') : url('/');
@endphp

@include('errors._page', [
    'statusCode' => 500,
    'title' => __('errors.500.title'),
    'body' => __('errors.500.body'),
    'primaryUrl' => request()->fullUrl(),
    'primaryLabel' => __('errors.500.action'),
    'secondaryUrl' => $homeUrl,
    'secondaryLabel' => __('errors.500.secondary_action'),
])
