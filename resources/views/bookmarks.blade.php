<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-24 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">
        <div class="w-full max-w-3xl mx-auto px-2 sm:px-6">
            <div class="flex items-center gap-4 mb-6 px-2">
                <a href="{{ route('feed') }}" class="p-2 hover:bg-gray-200 dark:hover:bg-[#242424] rounded-full transition-colors text-black dark:text-white" aria-label="Kembali ke feed">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="font-black text-xl text-black dark:text-white uppercase">Tersimpan</h1>
                    <p class="font-bold text-sm text-gray-500">Utas yang kamu simpan</p>
                </div>
            </div>

            @if ($posts->isEmpty())
                <div class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8 text-center">
                    <div class="text-6xl mb-4 opacity-50">🔖</div>
                    <p class="font-black uppercase text-black dark:text-white">Belum ada utas tersimpan</p>
                </div>
            @else
                <div class="space-y-5 sm:space-y-6">
                    @foreach ($posts as $post)
                        @php
                            $isRepost = $post->repost_id !== null;
                            $displayPost = $isRepost ? $post->originalPost : $post;
                            $modernMedia = is_array($displayPost?->images) ? $displayPost->images : [];
                            $mediaPaths = [];

                            foreach ($modernMedia as $mediaPath) {
                                if (is_string($mediaPath) && trim($mediaPath) !== '' && ! in_array($mediaPath, $mediaPaths, true)) {
                                    $mediaPaths[] = $mediaPath;
                                }
                            }

                            if ($mediaPaths === [] && is_string($displayPost?->image) && trim($displayPost->image) !== '') {
                                $mediaPaths[] = $displayPost->image;
                            }
                        @endphp

                        <article class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-4 sm:p-5 transition-colors hover:bg-gray-50 dark:hover:bg-[#141414]">
                            @if ($isRepost)
                                <div class="flex items-center gap-2 mb-3 text-gray-500 dark:text-gray-400 font-black text-[11px] uppercase tracking-wider">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    <span>{{ $post->user->username }} memposting ulang</span>
                                </div>
                            @endif

                            <div class="flex gap-3 sm:gap-4">
                                <a href="{{ route('user.profile', $displayPost->user->username) }}" class="flex-shrink-0">
                                    @if ($displayPost->user->avatar)
                                        <img src="{{ asset('storage/' . $displayPost->user->avatar) }}" alt="Avatar {{ $displayPost->user->name }}" class="h-10 w-10 sm:h-12 sm:w-12 object-cover border-2 border-black rounded-full">
                                    @else
                                        <div class="h-10 w-10 sm:h-12 sm:w-12 border-2 border-black bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-base sm:text-lg">
                                            {{ substr($displayPost->user->name, 0, 1) }}
                                        </div>
                                    @endif
                                </a>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap mb-2">
                                        <a href="{{ route('user.profile', $displayPost->user->username) }}" class="font-black text-sm sm:text-base text-black dark:text-white hover:underline">
                                            {{ $displayPost->user->username }}
                                        </a>
                                        <span class="text-[11px] sm:text-xs font-bold text-gray-500">
                                            {{ $displayPost->created_at->locale('id')->diffForHumans(null, true) }}
                                        </span>
                                    </div>

                                    <a href="{{ route('posts.show', $displayPost->uid) }}" class="block">
                                        <p class="text-black dark:text-white font-medium text-[15px] sm:text-base leading-relaxed whitespace-pre-wrap mb-3">{{ $displayPost->content }}</p>

                                        @if ($mediaPaths !== [])
                                            <div class="flex gap-2 overflow-x-auto mb-3">
                                                @foreach ($mediaPaths as $mediaPath)
                                                    <div class="shrink-0 w-[78%] sm:w-[48%] aspect-[4/5] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] overflow-hidden">
                                                        <img src="{{ asset('storage/' . $mediaPath) }}" alt="Foto dari {{ $displayPost->user->name }}" class="w-full h-full object-cover">
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </a>

                                    <div class="flex items-center justify-between gap-3 border-t-2 border-dashed border-gray-300 dark:border-[#333333] pt-3">
                                        <a href="{{ route('posts.show', $displayPost->uid) }}" class="text-xs font-black uppercase text-blue-600 dark:text-blue-400 hover:underline">
                                            Lihat utas
                                        </a>

                                        <form action="{{ route('bookmarks.destroy', $displayPost->uid) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Hapus dari tersimpan" title="Hapus dari tersimpan" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-black dark:border-[#333333] bg-yellow-300 text-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 3h12a1 1 0 011 1v17l-7-3.5L5 21V4a1 1 0 011-1z"></path></svg>
                                                <span class="text-[10px] sm:text-xs font-black uppercase">Hapus</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($posts->hasPages())
                    <div class="mt-6">
                        {{ $posts->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
