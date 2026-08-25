<nav x-data="{ open: false }" class="bg-white dark:bg-[#101010] border-b-4 border-black dark:border-[#333333] sticky top-0 z-50 transition-colors duration-300">
    <div class="w-full 2xl:max-w-[1600px] mx-auto px-4 sm:px-8 lg:px-12">
        <div class="flex justify-between h-16 sm:h-20">
            <!-- KIRI: Logo SkuySocial -->
            <div class="flex items-center">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('feed') }}" class="font-black text-xl sm:text-2xl tracking-wider uppercase border-2 border-black px-3 py-1 shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] bg-yellow-300 text-black hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all">
                        SkuySocial
                    </a>
                </div>
            </div>
			
			<!-- NEO-BRUTALIST HEADER CONTROLS -->
			<div class="flex items-center gap-2">
    <!-- 1. Tombol Dark Mode (Tampil di HP & Desktop) -->
			<button x-data @click="document.documentElement.classList.toggle('dark')" type="button" class="bg-black dark:bg-[#242424] text-yellow-300 border-2 border-black dark:border-[#333333] p-1.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
			</button>
    
    <!-- 2. Tombol Hamburger Sidebar (HANYA tampil di HP, panggil @toggle-sidebar) -->
			<button x-data @click="$dispatch('toggle-sidebar')" type="button" class="md:hidden bg-white dark:bg-[#181818] text-black dark:text-white border-2 border-black dark:border-[#333333] p-1.5 shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] dark:shadow-[2px_2px_0px_0px_rgba(51,51,51,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
			</button>
			</div>

            <!-- KANAN: Tombol Dropdown Profil -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 font-black uppercase border-2 border-black px-4 py-2 bg-pink-400 text-black shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] transition-all text-xs sm:text-sm focus:outline-none">
                            <div>{{ Auth::user()->name }}</div>
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] dark:shadow-[4px_4px_0px_0px_rgba(51,51,51,1)]">
                            <x-dropdown-link :href="route('user.profile', auth()->id())" class="font-black text-black dark:text-white border-b-2 border-black dark:border-[#333333] hover:bg-emerald-400 dark:hover:bg-emerald-500 hover:text-black uppercase text-xs transition-colors">
                                Profil Saya
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')" class="font-black text-black dark:text-white border-b-2 border-black dark:border-[#333333] hover:bg-cyan-400 dark:hover:bg-cyan-500 hover:text-black uppercase text-xs transition-colors">
                                Pengaturan
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="font-black text-black dark:text-white hover:bg-red-400 dark:hover:bg-red-500 hover:text-black uppercase text-xs transition-colors">
                                    Keluar
                                </x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
            
            <!-- Hamburger HP -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-black dark:text-white hover:bg-yellow-300 dark:hover:bg-yellow-400 dark:hover:text-black border-2 border-transparent hover:border-black transition-all">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24"><path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="square" stroke-linejoin="miter" stroke-width="3" d="M4 6h16M4 12h16M4 18h16" /><path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="square" stroke-linejoin="miter" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Nav (HP) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t-4 border-black dark:border-[#333333] bg-white dark:bg-[#101010]">
        <div class="pt-4 pb-1 border-b-2 border-black dark:border-[#333333] border-dashed">
            <div class="px-4">
                <div class="font-black text-base text-black dark:text-white uppercase">{{ Auth::user()->name }}</div>
                <div class="font-medium text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('user.profile', auth()->id())" class="font-black text-black dark:text-white border-t-2 border-black dark:border-[#333333] uppercase text-sm hover:bg-emerald-400 dark:hover:bg-emerald-500 hover:text-black">
                    Profil Saya
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile.edit')" class="font-black text-black dark:text-white border-t-2 border-black dark:border-[#333333] uppercase text-sm hover:bg-cyan-400 dark:hover:bg-cyan-500 hover:text-black">
                    Pengaturan
                </x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();" class="font-black text-red-600 dark:text-red-400 border-t-2 border-black dark:border-[#333333] uppercase text-sm hover:bg-red-500 hover:text-white">
                        Keluar
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>