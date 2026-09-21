<x-guest-layout>
    <section class="skuy-auth-content" aria-labelledby="verify-email-heading">
        <div class="skuy-auth-intro">
            <h1 id="verify-email-heading" class="skuy-auth-heading">{{ __('auth.verify_email.title') }}</h1>
            <p class="skuy-auth-intro__copy">{{ __('auth.verify_email.intro') }}</p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="skuy-auth-status" role="status">
                {{ __('auth.verify_email.sent') }}
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <form method="POST" action="{{ route('verification.send') }}" class="min-w-0 flex-1">
                @csrf

                <x-primary-button class="skuy-auth-primary-action sm:w-auto">
                    {{ __('auth.verify_email.resend') }}
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="sm:shrink-0">
                @csrf

                <button type="submit" class="skuy-auth-link w-full justify-center text-center sm:w-auto">
                    {{ __('auth.common.log_out') }}
                </button>
            </form>
        </div>
    </section>
</x-guest-layout>
