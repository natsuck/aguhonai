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

            <div class="mt-5 space-y-5 text-sm leading-7 text-ink-muted">
                <p>
                    Aguhon processes account and assessment information to provide academic guidance. Please review
                    what the application collects, how automated services are used, and how to exercise your rights
                    before consenting.
                </p>

                <div>
                    <h3 class="font-bold text-primary">1. Data Collected</h3>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li>Your account information (name and email address).</li>
                        <li>Your 90 career-interest responses and 60 scholastic aptitude responses.</li>
                        <li>Your computed RIASEC and aptitude scores, Program Suitability Scores, and assessment history.</li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-bold text-primary">2. Assessment and AI Processing</h3>
                    <p class="mt-2">
                        Your responses are scored by the application. The resulting score profile and program
                        descriptions are sent to the configured AI provider (OpenAI when selected) to generate an
                        explanation and exploratory suggestions. Your name, email address, and individual item
                        responses are not included in that assessment AI request. The ranked Program Suitability
                        Scores displayed in your results are calculated separately by the application from the
                        assessment profile and configurable program profiles; they are not AI-generated rankings.
                        These initial program profiles have not been expert-validated. Results are guidance only,
                        not a probability or prediction of academic success, and should not be the sole basis for
                        an enrollment decision.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary">3. Uploaded Study Materials</h3>
                    <p class="mt-2">
                        If you use the AI quiz feature, the text extracted from the study material you upload is sent
                        to the configured AI provider to generate quiz questions and flashcards. Do not upload
                        confidential information or personal data about another person unless you have permission
                        and are allowed to share it. Uploaded materials are associated with your account and can be
                        removed by deleting the quiz. Account deletion removes quiz records from the application
                        database but does not currently remove uploaded source files from storage. To remove an
                        uploaded file, delete its quiz before deleting your account.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary">4. Storage, Access, and Retention</h3>
                    <p class="mt-2">
                        Your responses and results are stored in the application database and remain associated with
                        your account until you delete your account, subject to applicable backup, log, and service
                        provider retention. Authorized school administrators can access identifiable account and
                        assessment information for platform administration. Deleting your account from Profile
                        removes your account and its associated assessment and quiz records from the application
                        database. It does not currently remove uploaded quiz source files from storage; delete
                        quizzes before deleting your account if you want those files removed. This application does
                        not specify or control how long deployment backups, operational logs, or the configured AI
                        provider may retain their copies; those periods depend on the hosting and provider settings
                        and policies.
                    </p>
                </div>

                <div>
                    <h3 class="font-bold text-primary">5. Your Choices and Privacy Rights</h3>
                    <p class="mt-2">
                        Participation in the assessment is voluntary. You may access and update your account
                        information through Profile and delete your account there; deletion removes associated
                        application records as described above. You may also request access, correction, or
                        information about processing from the school or platform administrator responsible for your
                        account. Under the Data Privacy Act of 2012, you may exercise applicable privacy rights or
                        lodge a complaint with the National Privacy Commission. The platform has not configured a
                        separate privacy contact on this page, so use the support channel provided by your school
                        or platform administrator.
                    </p>
                </div>
            </div>

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
