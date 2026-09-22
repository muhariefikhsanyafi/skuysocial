<section>
    <header>
        <h2 class="text-xl font-bold text-[var(--skuy-text-primary)]">
            {{ __('settings.profile.heading') }}
        </h2>

        <p class="mt-2 text-sm leading-6 text-[var(--skuy-text-secondary)]">
            {{ __('settings.profile.description') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-8 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div class="space-y-2">
            <x-input-label for="name" :value="__('settings.profile.name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div class="space-y-2">
            <x-input-label for="username" :value="__('settings.profile.username')" />
            <x-text-input
                id="username"
                name="username"
                type="text"
                class="mt-1"
                :value="old('username', $user->username)"
                required
                autocomplete="username"
                oninput="this.value = this.value.replace(/\s/g, '').toLowerCase()"
                pattern="^[a-zA-Z0-9_.]+$"
                title="{{ __('settings.profile.username_format') }}"
            />
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>

        <div class="space-y-2">
            <x-input-label for="email" :value="__('settings.profile.email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="space-y-2">
                    <p class="text-sm leading-6 text-[var(--skuy-text-secondary)]">
                        {{ __('settings.profile.email_unverified') }}

                        <button form="send-verification" type="submit" class="inline-flex min-h-[44px] items-center rounded-[var(--radius-small)] text-sm font-semibold text-[var(--skuy-secondary)] underline underline-offset-4 hover:text-[var(--skuy-secondary-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--skuy-focus)]">
                            {{ __('settings.profile.resend_verification') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="text-sm font-semibold text-[var(--skuy-secondary)]" role="status" aria-live="polite">
                            {{ __('settings.profile.verification_sent') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-2">
            <x-input-label for="bio" :value="__('settings.profile.bio_short')" />
            <textarea id="bio" name="bio" rows="3" class="mt-1 block min-h-[112px] w-full resize-y rounded-[var(--radius-small)] border border-[var(--skuy-border-strong)] bg-[var(--skuy-surface)] px-3 py-3 text-[var(--skuy-text-primary)] placeholder:text-[var(--skuy-text-muted)] focus:border-[var(--skuy-focus)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 focus:ring-offset-[var(--skuy-surface)]" placeholder="{{ __('settings.profile.bio_placeholder') }}">{{ old('bio', $user->bio) }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('bio')" />
        </div>

        <div class="space-y-3">
            <x-input-label for="avatar" :value="__('settings.profile.avatar')" />

            @if ($user->avatar)
                <div class="inline-flex h-24 w-24 overflow-hidden rounded-full border border-[var(--skuy-border-subtle)] bg-[var(--skuy-selected)]">
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ __('threads.media.avatar') }}" class="h-full w-full object-cover">
                </div>
            @endif

            <input type="file" id="avatar" name="avatar" accept="image/*" class="block min-h-[48px] w-full rounded-[var(--radius-small)] border border-dashed border-[var(--skuy-border-strong)] bg-[var(--skuy-surface-subtle)] px-3 py-2 text-sm text-[var(--skuy-text-primary)] file:mr-3 file:rounded-[var(--radius-small)] file:border file:border-[var(--skuy-secondary)] file:bg-[var(--skuy-selected)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--skuy-on-selected)] hover:file:bg-[var(--skuy-secondary)] hover:file:text-[var(--skuy-on-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--skuy-focus)] focus:ring-offset-2 focus:ring-offset-[var(--skuy-surface)]">
            <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
        </div>

        <div class="flex flex-col-reverse items-stretch gap-3 border-t border-[var(--skuy-border-subtle)] pt-6 sm:flex-row sm:items-center sm:justify-end">
            <x-primary-button>
                {{ __('settings.profile.save') }}
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm font-semibold text-[var(--skuy-secondary)]" role="status" aria-live="polite">
                    {{ __('settings.profile.saved') }}
                </p>
            @endif
        </div>
    </form>
</section>
