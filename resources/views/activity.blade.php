<x-app-layout>
    <div class="skuy-feed-page">
        <div class="skuy-feed-content mx-auto w-full max-w-2xl px-4 py-8 sm:px-6">
            <!-- Header Halaman -->
            <div class="mb-6 flex items-center gap-3 border-b border-[var(--skuy-border-subtle)] pb-3">
                <a href="{{ route('feed') }}" aria-label="{{ __('navigation.back_to_feed') }}" class="skuy-action">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19 3 12l7-7M3 12h18"></path>
                    </svg>
                </a>
                <h1 class="text-xl font-bold text-[var(--skuy-text-primary)]">{{ __('activity.title') }}</h1>
            </div>

            <!-- Daftar Notifikasi -->
            <div class="space-y-3">

            <!-- Cek apakah data notifikasi kosong menggunakan IF standar -->
            @if($notifications->isEmpty())
                <!-- Tampilan jika tidak ada notifikasi -->
                <div class="skuy-profile-empty" role="status">
                    <div class="mb-4 text-5xl opacity-60" aria-hidden="true">📭</div>
                    <h2 class="skuy-profile-empty__title">{{ __('activity.empty_title') }}</h2>
                    <p class="skuy-profile-empty__description">{{ __('activity.empty_description') }}</p>
                </div>

            @else
                <!-- Lakukan perulangan jika ada data -->
                @foreach ($notifications as $notification)
                    @php
                        // Ambil data yang dikirim oleh kurir
                        $data = $notification->data;
                        $postRouteKey = $data['post_slug'] ?? null;
                        $postTargetUrl = null;

                        if (
                            in_array($data['type'] ?? null, ['like', 'comment'], true)
                            && is_string($postRouteKey)
                            && $postRouteKey !== ''
                        ) {
                            $postTargetUrl = route('posts.show', $postRouteKey);
                        }
                    @endphp

                    <div class="flex items-start gap-3 rounded-[var(--radius-medium)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-surface)] p-4 transition-colors hover:bg-[var(--skuy-surface-subtle)]">
                        <!-- Avatar Pembuat Aktivitas -->
                        <a href="{{ route('user.profile', $data['username']) }}" class="skuy-avatar skuy-avatar--small shrink-0" aria-label="{{ __('threads.media.avatar_of', ['name' => $data['name']]) }}">
                            <img src="{{ isset($data['avatar']) ? asset('storage/' . $data['avatar']) : 'https://ui-avatars.com/api/?name='.urlencode($data['name']).'&background=random&color=fff&font-weight=bold' }}"
                                 alt="{{ $data['name'] }}"
                                 class="object-cover">
                        </a>

                        <!-- Isi Pesan Notifikasi -->
                        <div class="min-w-0 flex-1 pt-0.5">
                            <p class="text-sm leading-6 text-[var(--skuy-text-primary)] sm:text-base">
                                <a href="{{ route('user.profile', $data['username']) }}" class="font-semibold text-[var(--skuy-text-primary)] transition-colors hover:text-[var(--skuy-secondary)] hover:underline">
                                    {{ $data['name'] }}
                                </a>
                                {{ $data['message'] }}
                            </p>
                            <span class="mt-1 block text-xs font-medium text-[var(--skuy-text-muted)]">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>
                            @if($postTargetUrl)
                                <a href="{{ $postTargetUrl }}" class="mt-2 inline-flex min-h-[32px] items-center text-xs font-semibold text-[var(--skuy-secondary)] underline underline-offset-2 transition-colors hover:text-[var(--skuy-secondary-hover)]">
                                    {{ __('activity.view_thread') }}
                                </a>
                            @endif
                        </div>

                        <!-- Ikon Penanda Jenis Aktivitas -->
                        <div class="shrink-0">
                            @if($data['type'] === 'like')
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-reaction)] text-[var(--skuy-on-reaction)]">
                                    <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z"></path></svg>
                                </div>
                            @elseif($data['type'] === 'comment')
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-secondary)] text-[var(--skuy-on-secondary)]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-8 7.5 8.4 8.4 0 0 1-3.5-.75L4 20l1.35-3.6A7.3 7.3 0 0 1 4 11.5 7.5 7.5 0 0 1 12 4a7.5 7.5 0 0 1 8 7.5Z"></path></svg>
                                </div>
                            @elseif($data['type'] === 'follow')
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-secondary)] text-[var(--skuy-on-secondary)]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"></circle><path stroke-linecap="round" d="M2 21a7 7 0 0 1 14 0"></path><path stroke-linecap="round" d="M18 8v6M15 11h6"></path></svg>
                                </div>
                            @elseif($data['type'] === 'repost')
                                <div class="flex h-10 w-10 items-center justify-center rounded-[var(--radius-small)] border border-[var(--skuy-border-subtle)] bg-[var(--skuy-secondary)] text-[var(--skuy-on-secondary)]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h13m-3-3 3 3-3 3M20 17H7m3 3-3-3 3-3"></path></svg>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            @if($notifications->hasPages())
                <div class="mt-6">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
