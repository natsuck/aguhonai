<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4" x-data="{ visible: false }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full pr-16"
                                type="password"
                                x-bind:type="visible ? 'text' : 'password'"
                                name="password"
                                required autocomplete="current-password" />
                <button type="button"
                        x-on:click="visible = !visible"
                        x-bind:aria-pressed="visible"
                        x-bind:aria-label="visible ? 'Hide password' : 'Show password'"
                        x-text="visible ? 'Hide' : 'Show'"
                        class="absolute inset-y-0 right-0 px-4 text-sm font-semibold text-primary-container hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent"
                        aria-controls="password">Show</button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-ink-muted hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-accent" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <div class="flex items-center justify mt-4">
            <x-primary-button class="w-full justify-center">
                {{ __('Log in') }}
            </x-primary-button>
        </div>

        <p class="text-center text-sm text-ink-muted mt-5">
            Don't have an account?
            <a href="{{ route('register') }}" class="font-semibold text-primary-container hover:text-primary underline">
                {{ __('Sign up') }}
            </a>
        </p>
    </form>
</x-guest-layout>
