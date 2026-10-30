<x-app-layout title="Data Privacy Consent">
    <div class="flex-grow w-full max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-10 flex flex-col gap-6">

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row justify-between sm:items-end gap-4 pb-4 border-b border-surface-low">
            <div>
                <h1 class="font-headline text-2xl sm:text-3xl font-bold text-primary">Data Privacy Consent</h1>
                <p class="text-sm text-ink-muted mt-1">Required under Republic Act No. 10173 (Data Privacy Act of 2012) before taking the assessment.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-sm font-semibold text-primary-container hover:text-primary shrink-0">Back to dashboard</a>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                You must tick the consent checkbox below before continuing.
            </div>
        @endif

        <section class="card p-6 sm:p-8 mb-8">
            <h2 class="font-headline text-lg font-bold text-primary">Privacy Notice</h2>

            @include('legal.partials.privacy-notice')

            <form method="POST" action="{{ route('assessment.consent.store') }}" class="mt-8">
                @csrf

                <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-surface border border-surface-low p-4 transition hover:border-accent hover:bg-accent/10">
                    <input type="checkbox" name="privacy_consent" value="1" required @checked(old('privacy_consent')) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-accent">
                    <span class="text-sm font-medium text-ink">
                        I have read and understood this privacy notice. I voluntarily consent to the collection and
                        processing of my assessment data for academic guidance purposes.
                    </span>
                </label>

                <button type="submit" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-accent hover:bg-accent-hover px-5 py-2.5 text-sm font-bold text-primary shadow-md transition-all">
                    Accept and Continue to Assessment
                    <x-icon name="chevron-right" class="w-4 h-4"/>
                </button>
            </form>
        </section>
    </div>
</x-app-layout>
