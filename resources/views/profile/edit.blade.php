<x-app-layout>
    <x-slot name="header">
        <div class="border-b-4 border-[var(--skuy-border-strong)] pb-2">
            <h2 class="font-black text-2xl text-[var(--skuy-text-primary)] uppercase tracking-widest">
                {{ __('settings.title') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12 bg-[var(--skuy-background)] min-h-screen">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-12">
            
            <!-- Kotak 1: Update Informasi Profil -->
            <div class="bg-[var(--skuy-surface)] border-4 border-[var(--skuy-border-strong)] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <!-- Kotak 2: Update Password -->
            <div class="bg-[var(--skuy-surface)] border-4 border-[var(--skuy-border-strong)] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <!-- Kotak 3: Hapus Akun -->
            <div class="bg-[var(--skuy-danger)] border-4 border-[var(--skuy-border-strong)] shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] p-8">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
