<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-headline font-bold text-primary">{{ __('Create your account') }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('Sign up to save your progress and get started.') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" x-data="passwordStrength()">
        @csrf

        <div>
            <x-input-label for="first_name" :value="__('First Name')" />
            <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name" :value="old('first_name')" required autofocus autocomplete="given-name" />
            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="last_name" :value="__('Last Name')" />
            <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name" :value="old('last_name')" required autocomplete="family-name" />
            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="birthday" :value="__('Birthday')" />
            <x-text-input id="birthday" class="block mt-1 w-full" type="date" name="birthday" :value="old('birthday')" required autocomplete="bday" />
            <p class="mt-1 text-xs text-ink-muted">{{ __('You must be at least 13 years old to create an account.') }}</p>
            <x-input-error :messages="$errors->get('birthday')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="gender" :value="__('Gender')" />
            <select id="gender" name="gender" class="block mt-1 w-full rounded-md border-surface-high shadow-sm focus:border-primary focus:ring-primary" required>
                <option value="" disabled @selected(old('gender') === null)>{{ __('Select gender') }}</option>
                <option value="male" @selected(old('gender') === 'male')>{{ __('Male') }}</option>
                <option value="female" @selected(old('gender') === 'female')>{{ __('Female') }}</option>
                <option value="others" @selected(old('gender') === 'others')>{{ __('Others') }}</option>
            </select>
            <x-input-error :messages="$errors->get('gender')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4" x-data="{ visible: false }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1">
                <x-text-input id="password" class="block w-full pr-16"
                                type="password"
                                x-bind:type="visible ? 'text' : 'password'"
                                name="password"
                                x-model="password"
                                minlength="8"
                                aria-describedby="password-strength-help password-strength-status"
                                required autocomplete="new-password" />
                <button type="button"
                        x-on:click="visible = !visible"
                        x-bind:aria-pressed="visible"
                        x-bind:aria-label="visible ? 'Hide password' : 'Show password'"
                        x-text="visible ? 'Hide' : 'Show'"
                        class="absolute inset-y-0 right-0 px-4 text-sm font-semibold text-primary-container hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent"
                        aria-controls="password">Show</button>
            </div>
            <p id="password-strength-help" class="mt-2 text-xs text-ink-muted">
                {{ __('Use at least 8 characters, including uppercase and lowercase letters, a number, and a symbol.') }}
            </p>
            <div class="mt-3" aria-hidden="true">
                <div class="flex gap-1">
                    <template x-for="segment in 4" :key="segment">
                        <span class="h-1.5 flex-1 rounded-full"
                              :class="score >= segment ? strengthColor : 'bg-surface-high'"></span>
                    </template>
                </div>
            </div>
            <p id="password-strength-status" class="mt-1 text-xs text-ink-muted" aria-live="polite" x-text="strengthLabel"></p>
            <ul class="mt-2 space-y-1 text-xs" aria-label="{{ __('Password requirements') }}">
                <template x-for="requirement in requirements" :key="requirement.label">
                    <li class="flex items-center gap-2"
                        :class="requirement.met ? 'text-emerald-700' : 'text-ink-muted'">
                        <span aria-hidden="true" x-text="requirement.met ? '✓' : '•'"></span>
                        <span x-text="requirement.label"></span>
                    </li>
                </template>
            </ul>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4" x-data="{ visible: false }">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <div class="relative mt-1">
                <x-text-input id="password_confirmation" class="block w-full pr-16"
                                type="password"
                                x-bind:type="visible ? 'text' : 'password'"
                                name="password_confirmation" required autocomplete="new-password" />
                <button type="button"
                        x-on:click="visible = !visible"
                        x-bind:aria-pressed="visible"
                        x-bind:aria-label="visible ? 'Hide password' : 'Show password'"
                        x-text="visible ? 'Hide' : 'Show'"
                        class="absolute inset-y-0 right-0 px-4 text-sm font-semibold text-primary-container hover:text-primary focus:outline-none focus:ring-2 focus:ring-accent"
                        aria-controls="password_confirmation">Show</button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify mt-4">
            <x-primary-button class="w-full justify-center">
                {{ __('Create account') }}
            </x-primary-button>
        </div>

        <p class="text-center text-sm text-ink-muted mt-5">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold text-primary-container hover:text-primary underline">
                {{ __('Log in') }}
            </a>
        </p>
    </form>
</x-guest-layout>
