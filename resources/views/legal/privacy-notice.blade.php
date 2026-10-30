<x-guest-layout>
    <div class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 border-b border-surface-low pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="font-headline text-2xl font-bold text-primary">{{ __('Privacy Notice') }}</h1>
                <p class="mt-1 text-sm text-ink-muted">
                    {{ __('How Aguhon handles account, assessment, and study material information.') }}
                </p>
            </div>
            <a href="{{ route('register') }}" class="shrink-0 text-sm font-semibold text-primary-container underline hover:text-primary">
                {{ __('Back to registration') }}
            </a>
        </div>

        @include('legal.partials.privacy-notice')
    </div>
</x-guest-layout>
