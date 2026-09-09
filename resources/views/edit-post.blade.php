<x-app-layout>
    <x-slot name="header">
        <div class="border-b-4 border-black pb-2">
            <h2 class="font-black text-2xl text-black uppercase tracking-widest">
                {{ __('threads.edit.title') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            
            <div class="bg-yellow-300 border-4 border-black shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] mb-12">
                <div class="p-6">
                    <form action="{{ route('posts.update', $post->uid) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <textarea 
                            name="content" 
                            rows="4" 
                            class="w-full bg-white border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] focus:ring-0 focus:outline-none focus:bg-yellow-50 text-black font-bold p-4 resize-none transition-colors" 
                            required>{{ $post->content }}</textarea>
                        
                        <div class="flex justify-between mt-4">
                            <!-- Tombol Batal -->
                            <a href="{{ route('feed') }}" class="bg-gray-300 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] text-black font-black uppercase tracking-wider py-2 px-8 transition-all">
                                {{ __('threads.actions.cancel') }}
                            </a>
                            <!-- Tombol Simpan -->
                            <button type="submit" class="bg-blue-400 border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-y-[4px] hover:translate-x-[4px] text-black font-black uppercase tracking-wider py-2 px-8 transition-all">
                                {{ __('threads.actions.save_changes') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
