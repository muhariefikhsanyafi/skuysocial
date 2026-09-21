<x-app-layout>
    <x-slot name="header">
        <div class="border-b-4 border-[var(--skuy-border-strong)] pb-2">
            <h2 class="font-black text-2xl text-[var(--skuy-text-primary)] uppercase tracking-widest">
                {{ __('threads.edit.title') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-[var(--skuy-background)] min-h-screen">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-[var(--skuy-surface)] border-4 border-[var(--skuy-border-strong)] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] mb-12">
                <div class="p-6">
                    <form action="{{ route('posts.update', $post->uid) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <textarea 
                            name="content" 
                            rows="4" 
                            class="w-full bg-[var(--skuy-surface)] border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:bg-[var(--skuy-selected)] focus:border-[var(--skuy-focus)] text-[var(--skuy-text-primary)] font-bold p-4 resize-none transition-colors"
                            required>{{ $post->content }}</textarea>
                        
                        <div class="flex justify-between mt-4">
                            <!-- Tombol Batal -->
                            <a href="{{ route('feed') }}" class="bg-[var(--skuy-surface-subtle)] border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] text-[var(--skuy-text-primary)] font-black uppercase tracking-wider py-2 px-8 transition-all">
                                {{ __('threads.actions.cancel') }}
                            </a>
                            <!-- Tombol Simpan -->
                            <button type="submit" class="bg-[var(--skuy-brand)] hover:bg-[var(--skuy-brand-hover)] border-4 border-[var(--skuy-border-strong)] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] text-[var(--skuy-on-brand)] font-black uppercase tracking-wider py-2 px-8 transition-all">
                                {{ __('threads.actions.save_changes') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
