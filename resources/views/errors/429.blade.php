@php
    $user = auth()->user();
    $homeUrl = $user instanceof \App\Models\User ? route('feed') : url('/');
@endphp

@include('errors._page', [
    'statusCode' => 429,
    'title' => __('errors.429.title'),
    'body' => __('errors.429.body'),
    'primaryUrl' => $homeUrl,
    'primaryLabel' => __('errors.429.action'),
])
