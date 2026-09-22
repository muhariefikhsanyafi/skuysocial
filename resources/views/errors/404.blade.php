@php
    $homeUrl = auth()->check() ? route('feed') : url('/');
@endphp

@include('errors._page', [
    'statusCode' => 404,
    'title' => __('errors.404.title'),
    'body' => __('errors.404.body'),
    'primaryUrl' => $homeUrl,
    'primaryLabel' => __('errors.404.action'),
])
