<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-24 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">
        <div class="w-full max-w-2xl lg:max-w-3xl mx-auto px-2 sm:px-6">
            <div class="flex items-center gap-4 mb-6 px-2">
                <a href="{{ route('user.profile', $profileUser) }}" aria-label="{{ __('navigation.back_to_profile') }}" class="p-2 hover:bg-gray-200 dark:hover:bg-[#242424] rounded-full transition-colors text-black dark:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="font-black text-xl text-black dark:text-white uppercase">{{ $title }}</h1>
                    <p class="font-bold text-sm text-gray-500">{{ '@' }}{{ $profileUser->username }}</p>
                </div>
            </div>

            @if ($users->isEmpty())
                <div class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8 text-center">
                    <p class="font-black uppercase text-black dark:text-white">{{ $emptyMessage }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($users as $listedUser)
                        @php($isFollowed = $followedUserIds->contains($listedUser->id))

                        <div class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-3 sm:p-4 flex items-center gap-3 sm:gap-4 hover:bg-cyan-100 dark:hover:bg-[#242424] transition-colors">
                            <a href="{{ route('user.profile', $listedUser) }}" class="flex items-center gap-3 min-w-0 flex-1">
                                @if ($listedUser->avatar)
                                    <img src="{{ asset('storage/' . $listedUser->avatar) }}" alt="{{ __('threads.media.avatar_of', ['name' => $listedUser->name]) }}" class="w-12 h-12 rounded-full border-2 border-black object-cover bg-gray-200 flex-shrink-0">
                                @else
                                    <div class="w-12 h-12 rounded-full border-2 border-black bg-cyan-400 flex items-center justify-center text-black font-black uppercase text-lg flex-shrink-0">
                                        {{ substr($listedUser->name, 0, 1) }}
                                    </div>
                                @endif

                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="font-black text-black dark:text-white leading-tight truncate">{{ $listedUser->name }}</p>
                                        @if ($listedUser->is_verified)
                                            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-500 border-2 border-black text-white text-[10px] font-black leading-none" title="{{ __('threads.verified') }}">✓</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 font-bold truncate">{{ '@' }}{{ $listedUser->username }}</p>
                                </div>
                            </a>

                            @if (auth()->id() !== $listedUser->id)
                                <form action="{{ route('user.follow', $listedUser) }}" method="POST" class="flex-shrink-0">
                                    @csrf
                                    <button type="submit" class="px-3 py-2 sm:px-4 border-2 border-black font-black uppercase text-[11px] sm:text-xs shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all {{ $isFollowed ? 'bg-white text-black' : 'bg-black text-white hover:bg-gray-800' }}">
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
