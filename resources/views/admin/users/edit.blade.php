<x-app-layout title="Manage User · Admin">
    <x-admin-layout active="users">
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-primary-container underline">Back to user accounts</a>
                    <h1 class="mt-3 font-headline text-2xl sm:text-3xl font-bold text-primary">Manage user</h1>
                    <p class="mt-1 text-sm text-ink-muted">{{ $user->email }} · {{ $user->isAdmin() ? 'Administrator' : 'Student' }}</p>
                </div>
                <span class="w-fit rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $user->hasVerifiedEmail() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $user->hasVerifiedEmail() ? 'Email verified' : 'Email unverified' }}
                </span>
            </header>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    <p class="font-semibold">Please review the following:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="card p-6 sm:p-8">
                <h2 class="font-headline text-lg font-bold text-primary">Profile information</h2>
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-semibold text-primary-container">First name</label>
                            <input id="first_name" name="first_name" type="text" maxlength="125"
                                   value="{{ old('first_name', $user->first_name ?? $user->name) }}" autocomplete="given-name"
                                   class="mt-2 block w-full rounded-xl border-surface-high bg-white text-ink shadow-sm focus:border-accent focus:ring-accent">
                            @error('first_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-semibold text-primary-container">Last name</label>
                            <input id="last_name" name="last_name" type="text" maxlength="125"
                                   value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name"
                                   class="mt-2 block w-full rounded-xl border-surface-high bg-white text-ink shadow-sm focus:border-accent focus:ring-accent">
                            @error('last_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-semibold text-primary-container">Email</label>
                            <input id="email" name="email" type="email" required maxlength="255"
                                   value="{{ old('email', $user->email) }}" autocomplete="email"
                                   class="mt-2 block w-full rounded-xl border-surface-high bg-white text-ink shadow-sm focus:border-accent focus:ring-accent">
                            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="birthday" class="block text-sm font-semibold text-primary-container">Birthday</label>
                            <input id="birthday" name="birthday" type="date"
                                   value="{{ old('birthday', $user->birthday?->format('Y-m-d')) }}" autocomplete="bday"
                                   class="mt-2 block w-full rounded-xl border-surface-high bg-white text-ink shadow-sm focus:border-accent focus:ring-accent">
                            @error('birthday') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="gender" class="block text-sm font-semibold text-primary-container">Gender</label>
                            <select id="gender" name="gender"
                                    class="mt-2 block w-full rounded-xl border-surface-high bg-white text-ink shadow-sm focus:border-accent focus:ring-accent">
                                <option value="" @selected(old('gender', $user->gender) === null || old('gender', $user->gender) === '')>Not specified</option>
                                <option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option>
                                <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                                <option value="others" @selected(old('gender', $user->gender) === 'others')>Others</option>
                            </select>
                            @error('gender') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <p class="text-xs leading-relaxed text-ink-muted">Changing the email address clears its verification status. Users must verify their new address before continuing.</p>
                    <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-container">Save profile</button>
                </form>
            </section>

            <section class="card p-6 sm:p-8">
                <h2 class="font-headline text-lg font-bold text-primary">Password recovery</h2>
                <p class="mt-1 text-sm leading-relaxed text-ink-muted">Send a password reset link to the user’s email. You cannot view or set their password.</p>
                <form method="POST" action="{{ route('admin.users.password-reset', $user) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-xl border border-surface-high bg-white px-4 py-2.5 text-sm font-semibold text-primary-container transition hover:bg-surface-low">
                        Send password reset link
                    </button>
                </form>
            </section>

            <section class="card p-6 sm:p-8">
                <h2 class="font-headline text-lg font-bold text-primary">Email verification</h2>
                <p class="mt-1 text-sm leading-relaxed text-ink-muted">
                    @if ($user->hasVerifiedEmail())
                        This email address is already verified.
                    @else
                        Mark this email address as verified after confirming the user's identity.
                    @endif
                </p>
                @unless ($user->hasVerifiedEmail())
                    <form method="POST" action="{{ route('admin.users.verify-email', $user) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                            Mark email as verified
                        </button>
                    </form>
                @endunless
            </section>

            <section class="rounded-2xl border border-rose-200 bg-rose-50 p-6 sm:p-8">
                <h2 class="font-headline text-lg font-bold text-rose-800">Permanently delete account</h2>
                <p class="mt-1 text-sm leading-relaxed text-rose-700">This permanently deletes the account, quizzes, attempts, assessment results, and uploaded quiz materials. This cannot be undone.</p>
                @if (auth()->id() === $user->id)
                    <p class="mt-4 text-sm font-semibold text-rose-800">You cannot delete your own administrator account here.</p>
                @else
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('DELETE')
                        <div>
                            <label for="delete_confirmation" class="block text-sm font-semibold text-rose-800">
                                Type <span class="font-mono">{{ $user->email }}</span> to confirm
                            </label>
                            <input id="delete_confirmation" name="delete_confirmation" type="email" required autocomplete="off"
                                   class="mt-2 block w-full rounded-xl border-rose-300 bg-white text-ink shadow-sm focus:border-rose-500 focus:ring-rose-500">
                        </div>
                        <button type="submit" class="rounded-xl bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-800">
                            Permanently delete account
                        </button>
                    </form>
                @endif
            </section>
        </div>
    </x-admin-layout>
</x-app-layout>
