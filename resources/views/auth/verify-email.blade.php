<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-headline font-bold text-primary">{{ __('Verify your email') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">
            {{ __('We sent a 6-digit verification code to :email. Enter it below to finish creating your account.', ['email' => auth()->user()->email]) }}
        </p>
    </div>

    @if (session('status') === 'verification-code-sent')
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-800" role="status">
            {{ __('A new verification code has been sent to your email address.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.verify') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Verification code')" />
            <x-text-input id="code" class="mt-1 block w-full text-center text-xl tracking-[0.4em]"
                          type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                          pattern="[0-9]{6}" maxlength="6" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center">
            {{ __('Verify email') }}
        </x-primary-button>
    </form>

    <div class="mt-5 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <button type="submit" class="text-sm font-semibold text-primary-container underline hover:text-primary">
                {{ __('Resend code') }}
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-ink-muted hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
