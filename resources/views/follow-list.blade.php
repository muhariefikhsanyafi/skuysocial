<x-app-layout>
    <div class="skuy-feed-page">
        <div class="skuy-feed-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <div class="mb-6 flex items-center gap-3 border-b border-[var(--skuy-border-subtle)] pb-3">
                <a href="{{ route('user.profile', $profileUser) }}" aria-label="{{ __('navigation.back_to_profile') }}" class="skuy-action">
                    <x-fa-icon name="arrow-left-long" class="text-xl" />
                </a>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ $title }}</h1>
                    <p class="mt-1 text-sm text-[var(--skuy-text-muted)]">{{ '@' }}{{ $profileUser->username }}</p>
                </div>
            </div>

            @if ($users->isEmpty())
                <div class="skuy-profile-empty" role="status">
                    <p class="skuy-profile-empty__title">{{ $emptyMessage }}</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($users as $listedUser)
                        @php($isFollowed = $followedUserIds->contains($listedUser->id))

                        <div class="flex items-center gap-3 rounded-[var(--radius-medium)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-3 transition-colors hover:bg-[var(--skuy-surface-subtle)] sm:p-4">
                            <a href="{{ route('user.profile', $listedUser) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-[var(--radius-small)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                                @if ($listedUser->avatar)
                                    <div class="skuy-avatar shrink-0">
                                        <img src="{{ asset('storage/' . $listedUser->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $listedUser->name]) }}">
                                    </div>
                                @else
                                    <div class="skuy-avatar shrink-0" aria-label="{{ __('threads.media.avatar_of', ['name' => $listedUser->name]) }}">
                                        {{ substr($listedUser->name, 0, 1) }}
                                    </div>
                                @endif

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="skuy-post__display-name truncate">{{ $listedUser->name }}</p>
                                        @if ($listedUser->is_verified)
                                            <x-verified-badge />
                                        @endif
                                    </div>
                                    <p class="skuy-post__username truncate">{{ '@' }}{{ $listedUser->username }}</p>
                                </div>
                            </a>

                            @if (auth()->id() !== $listedUser->id)
                                <form action="{{ route('user.follow', $listedUser) }}" method="POST" class="shrink-0">
                                    @csrf
                                    <button
                                        type="submit"
                                        aria-pressed="{{ $isFollowed ? 'true' : 'false' }}"
                                        class="inline-flex min-h-[44px] items-center justify-center rounded-[var(--radius-medium)] border border-[var(--skuy-secondary)] px-4 py-2 text-sm font-bold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)] {{ $isFollowed ? 'bg-[var(--skuy-surface)] text-[var(--skuy-secondary)] hover:bg-[var(--skuy-surface-subtle)]' : 'bg-[var(--skuy-secondary)] text-[var(--skuy-on-secondary)] hover:bg-[var(--skuy-secondary-hover)]' }}"
                                    >
                                        {{ $isFollowed ? __('profile.following') : __('profile.follow') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($users->hasPages())
                    <div class="mt-6">
                        {{ $users->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
