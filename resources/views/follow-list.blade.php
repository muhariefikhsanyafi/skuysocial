<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-24 bg-[var(--skuy-background)]  min-h-screen transition-colors duration-300">
        <div class="w-full max-w-2xl lg:max-w-3xl mx-auto px-2 sm:px-6">
            <div class="flex items-center gap-4 mb-6 px-2">
                <a href="{{ route('user.profile', $profileUser) }}" aria-label="{{ __('navigation.back_to_profile') }}" class="p-2 hover:bg-[var(--skuy-surface-subtle)] dark:hover:bg-[var(--skuy-surface-subtle)] rounded-full transition-colors text-[var(--skuy-text-primary)] ">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="font-black text-xl text-[var(--skuy-text-primary)]  uppercase">{{ $title }}</h1>
                    <p class="font-bold text-sm text-[var(--skuy-text-muted)]">{{ '@' }}{{ $profileUser->username }}</p>
                </div>
            </div>

            @if ($users->isEmpty())
                <div class="bg-[var(--skuy-surface)]  border-2 sm:border-4 border-[var(--skuy-border-strong)]  shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8 text-center">
                    <p class="font-black uppercase text-[var(--skuy-text-primary)] ">{{ $emptyMessage }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($users as $listedUser)
                        @php($isFollowed = $followedUserIds->contains($listedUser->id))

                        <div class="bg-[var(--skuy-surface)]  border-2 sm:border-4 border-[var(--skuy-border-strong)]  shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-3 sm:p-4 flex items-center gap-3 sm:gap-4 hover:bg-[var(--skuy-selected)] dark:hover:bg-[var(--skuy-surface-subtle)] transition-colors">
                            <a href="{{ route('user.profile', $listedUser) }}" class="flex items-center gap-3 min-w-0 flex-1">
                                @if ($listedUser->avatar)
                                    <img src="{{ asset('storage/' . $listedUser->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $listedUser->name]) }}" class="w-12 h-12 rounded-full border-2 border-[var(--skuy-border-strong)] object-cover bg-[var(--skuy-surface-subtle)] flex-shrink-0">
                                @else
                                    <div class="w-12 h-12 rounded-full border-2 border-[var(--skuy-border-strong)] bg-[var(--skuy-selected)] flex items-center justify-center text-[var(--skuy-on-selected)] font-black uppercase text-lg flex-shrink-0">
                                        {{ substr($listedUser->name, 0, 1) }}
                                    </div>
                                @endif

                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="font-black text-[var(--skuy-text-primary)]  leading-tight truncate">{{ $listedUser->name }}</p>
                                        @if ($listedUser->is_verified)
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--skuy-secondary)] border-2 border-[var(--skuy-border-strong)] text-[var(--skuy-on-secondary)] text-[10px] font-black leading-none" title="{{ __('threads.verified') }}">✓</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-[var(--skuy-text-secondary)]  font-bold truncate">{{ '@' }}{{ $listedUser->username }}</p>
                                </div>
                            </a>

                            @if (auth()->id() !== $listedUser->id)
                                <form action="{{ route('user.follow', $listedUser) }}" method="POST" class="flex-shrink-0">
                                    @csrf
                                    <button type="submit" class="px-3 py-2 sm:px-4 border-2 border-[var(--skuy-border-strong)] font-black uppercase text-[11px] sm:text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $isFollowed ? 'bg-[var(--skuy-surface)] text-[var(--skuy-text-primary)]' : 'bg-[var(--skuy-brand)] text-[var(--skuy-on-brand)] hover:bg-[var(--skuy-brand-hover)]' }}">
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
