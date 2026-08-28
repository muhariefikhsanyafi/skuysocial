<x-app-layout>
    <div class="pt-4 sm:pt-12 pb-24 bg-gray-50 dark:bg-[#101010] min-h-screen transition-colors duration-300">
        <div class="w-full max-w-2xl lg:max-w-3xl mx-auto px-2 sm:px-6">
            
            <!-- Header Kembali -->
            <div class="flex items-center gap-4 mb-6 px-2">
                <a href="{{ route('user.profile', $user->username) }}" class="p-2 hover:bg-gray-200 dark:hover:bg-[#242424] rounded-full transition-colors text-black dark:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h1 class="font-black text-xl text-black dark:text-white uppercase">Edit Profil</h1>
            </div>

            <!-- Form Edit Profil dengan Alpine.js untuk Preview Gambar -->
            <form action="{{ route('user.update') }}" method="POST" enctype="multipart/form-data" 
                  x-data="{ 
                      coverPreview: '{{ $user->cover ? asset('storage/' . $user->cover) : '' }}',
                      avatarPreview: '{{ $user->avatar ? asset('storage/' . $user->avatar) : '' }}',
                      updateCover(event) {
                          const file = event.target.files[0];
                          if (file) this.coverPreview = URL.createObjectURL(file);
                      },
                      updateAvatar(event) {
                          const file = event.target.files[0];
                          if (file) this.avatarPreview = URL.createObjectURL(file);
                      },
                      isSubmitting: false
                  }" 
                  @submit="isSubmitting = true"
                  class="bg-white dark:bg-[#101010] border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] overflow-hidden relative">
                
                @csrf

                <!-- Area Edit Sampul (Cover) -->
                <div class="relative h-32 sm:h-48 bg-pink-400 border-b-2 sm:border-b-4 border-black dark:border-[#333333] group overflow-hidden">
                    <!-- Tampilan Cover -->
                    <template x-if="coverPreview">
                        <img :src="coverPreview" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!coverPreview">
                        <div class="w-full h-full flex items-center justify-center relative">
                            <div class="absolute -right-10 -top-10 text-9xl opacity-20 transform rotate-12">🚀</div>
                            <div class="absolute -left-10 -bottom-10 text-9xl opacity-20 transform -rotate-12">👾</div>
                        </div>
                    </template>
                    
                    <!-- Overlay Upload Hover -->
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center cursor-pointer">
                        <label class="cursor-pointer flex items-center gap-2 bg-white text-black font-black uppercase text-xs px-4 py-2 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Ubah Sampul
                            <input type="file" name="cover" class="hidden" accept="image/*" @change="updateCover">
                        </label>
                    </div>
                </div>

                <!-- Area Bawah (Avatar & Input Teks) -->
                <div class="px-4 sm:px-6 pb-6 sm:pb-8 relative">
                    
                    <!-- Area Edit Avatar -->
                    <div class="flex justify-between items-end -mt-12 sm:-mt-16 mb-6 relative z-10">
                        <div class="relative group cursor-pointer">
                            <div class="flex-shrink-0">
                                <template x-if="avatarPreview">
                                    <img :src="avatarPreview" class="h-24 w-24 sm:h-32 sm:w-32 object-cover border-4 border-black bg-white rounded-full shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] relative z-10">
                                </template>
                                <template x-if="!avatarPreview">
                                    <div class="h-24 w-24 sm:h-32 sm:w-32 border-4 border-black bg-cyan-400 rounded-full flex items-center justify-center text-black font-black uppercase text-4xl sm:text-6xl shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] relative z-10">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                </template>
                            </div>
                            <!-- Overlay Upload Hover -->
                            <label class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 rounded-full z-20 flex items-center justify-center cursor-pointer transition-opacity border-4 border-transparent">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <input type="file" name="avatar" class="hidden" accept="image/*" @change="updateAvatar">
                            </label>
                        </div>
                    </div>

                    <!-- Pesan Error Validasi -->
                    @if ($errors->any())
                        <div class="mb-6 p-4 bg-red-100 border-2 border-red-500 shadow-[2px_2px_0px_0px_rgba(239,68,68,1)]">
                            <ul class="list-disc list-inside text-red-600 text-xs sm:text-sm font-black uppercase">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Input Nama Tampilan -->
                    <div class="mb-5">
                        <label class="block text-black dark:text-white font-black uppercase text-xs sm:text-sm mb-2">Nama Tampilan</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-4 py-3 text-black dark:text-white font-bold focus:ring-0 focus:outline-none focus:border-pink-500 transition-colors shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[6px_6px_0px_0px_rgba(51,51,51,1)]" required maxlength="50">
                    </div>

                    <!-- Input Username (Hanya Baca / Disabled) -->
                    <div class="mb-5">
                        <label class="block text-gray-500 font-black uppercase text-xs sm:text-sm mb-2">Username (Tidak bisa diubah)</label>
                        <input type="text" value="{{ '@' . $user->username }}" class="w-full bg-gray-200 dark:bg-[#242424] border-2 border-black dark:border-[#333333] px-4 py-3 text-gray-500 font-bold shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[6px_6px_0px_0px_rgba(51,51,51,1)] cursor-not-allowed" disabled>
                    </div>

                    <!-- Input Bio -->
                    <div class="mb-8">
                        <label class="block text-black dark:text-white font-black uppercase text-xs sm:text-sm mb-2">Bio / Tentang Saya</label>
                        <textarea name="bio" rows="4" class="w-full bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] px-4 py-3 text-black dark:text-white font-bold focus:ring-0 focus:outline-none focus:border-cyan-500 transition-colors shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[6px_6px_0px_0px_rgba(51,51,51,1)] resize-none" placeholder="Tuliskan sesuatu tentang dirimu..." maxlength="160">{{ old('bio', $user->bio) }}</textarea>
                        <p class="text-xs font-bold text-gray-500 mt-2">*Maksimal 160 karakter</p>
                    </div>

                    <!-- Tombol Simpan -->
                    <div class="flex justify-end gap-3 pt-5 pb-4 border-t-2 border-dashed border-gray-300 dark:border-[#333333]">
                        <a href="{{ route('user.profile', $user->username) }}" class="px-5 sm:px-6 py-2.5 bg-white dark:bg-[#181818] border-2 border-black dark:border-[#333333] text-black dark:text-white font-black uppercase text-xs sm:text-sm shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all">
                            Batal
                        </a>
                        <button type="submit" :disabled="isSubmitting" class="px-5 sm:px-6 py-2.5 bg-yellow-300 border-2 border-black text-black font-black uppercase text-xs sm:text-sm shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:translate-x-[2px] hover:translate-y-[2px] hover:shadow-none transition-all disabled:bg-gray-400">
                            <span x-show="!isSubmitting">Simpan Perubahan</span>
                            <span x-show="isSubmitting">Menyimpan...</span>
                        </button>
                    </div>

                </div>
            </form
			
			<!-- ========================================== -->
            <!-- BLOK UBAH PASSWORD (CYAN) -->
            <!-- ========================================== -->
            <div class="mt-8 bg-cyan-400 dark:bg-cyan-500 border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8">
                <h2 class="font-black text-xl sm:text-2xl text-black uppercase mb-2">Update Password</h2>
                <p class="font-bold text-sm text-black mb-6">Pastikan akun Anda menggunakan password acak yang panjang agar tetap aman.</p>
                
                <!-- Kita arahkan ke rute bawaan Laravel Breeze -->
                <form action="{{ route('password.update') }}" method="POST">
                    @csrf
                    @method('put')
                    
                    <div class="mb-5">
                        <label class="block text-black font-black uppercase text-xs sm:text-sm mb-2">Password Saat Ini</label>
                        <input type="password" name="current_password" class="w-full bg-white border-2 border-black px-4 py-3 text-black font-bold focus:ring-0 focus:outline-none focus:border-pink-500 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-white bg-red-600 p-2 border-2 border-black font-black text-xs uppercase" />
                    </div>
                    
                    <div class="mb-5">
                        <label class="block text-black font-black uppercase text-xs sm:text-sm mb-2">Password Baru</label>
                        <input type="password" name="password" class="w-full bg-white border-2 border-black px-4 py-3 text-black font-bold focus:ring-0 focus:outline-none focus:border-pink-500 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-white bg-red-600 p-2 border-2 border-black font-black text-xs uppercase" />
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-black font-black uppercase text-xs sm:text-sm mb-2">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="w-full bg-white border-2 border-black px-4 py-3 text-black font-bold focus:ring-0 focus:outline-none focus:border-pink-500 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]">
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-white bg-red-600 p-2 border-2 border-black font-black text-xs uppercase" />
                    </div>
                    
                    <button type="submit" class="bg-black text-white px-6 py-3 font-black uppercase text-sm border-2 border-transparent hover:bg-white hover:text-black hover:border-black hover:-translate-y-1 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-all">
                        Simpan Password
                    </button>
                    
                    @if (session('status') === 'password-updated')
                        <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)" class="inline-block ml-4 text-sm font-black uppercase text-black bg-white px-3 py-1 border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]">
                            Tersimpan!
                        </p>
                    @endif
                </form>
            </div>

            <!-- ========================================== -->
            <!-- BLOK HAPUS AKUN (MERAH) -->
            <!-- ========================================== -->
            <div class="mt-8 bg-pink-400 dark:bg-pink-500 border-2 sm:border-4 border-black dark:border-[#333333] shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] sm:dark:shadow-[8px_8px_0px_0px_rgba(51,51,51,1)] p-6 sm:p-8">
                <h2 class="font-black text-xl sm:text-2xl text-black uppercase mb-2">Hapus Akun</h2>
                <p class="font-bold text-sm text-black mb-6">Sekali akun dihapus, semua sumber daya dan datanya akan musnah permanen. Masukkan password Anda untuk mengonfirmasi.</p>
                
                <form action="{{ route('profile.destroy') }}" method="POST">
                    @csrf
                    @method('delete')
                    
                    <div class="mb-6">
                        <label class="block text-black font-black uppercase text-xs sm:text-sm mb-2">Password Anda</label>
                        <input type="password" name="password" class="w-full bg-white border-2 border-black px-4 py-3 text-black font-bold focus:ring-0 focus:outline-none focus:border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] sm:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]" placeholder="Ketik password untuk melanjutkan..." required>
                        <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-white bg-black p-2 border-2 border-white font-black text-xs uppercase" />
                    </div>
                    
                    <button type="submit" onclick="return confirm('Apakah Anda YAKIN ingin menghapus akun ini secara permanen?')" class="bg-red-600 text-white px-6 py-3 font-black uppercase text-sm border-2 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] hover:translate-y-[2px] hover:translate-x-[2px] hover:shadow-none transition-all">
                        Hapus Akun Ini
                    </button>
                </form>
            </div>>

        </div>
    </div>
</x-app-layout>