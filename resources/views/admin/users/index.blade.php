<x-app-layout title="User Accounts · Admin">
    <x-admin-layout active="users">
        <div class="flex flex-col gap-8">
            <header>
                <h1 class="font-headline text-2xl sm:text-3xl font-bold text-primary">User Accounts</h1>
                <p class="mt-1 text-sm text-ink-muted">Manage user profiles, email verification, and password recovery.</p>
            </header>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <section class="card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Total accounts</p>
                    <p class="mt-2 text-3xl font-headline font-bold text-primary">{{ $totalUsers }}</p>
                </section>
                <section class="card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Awaiting verification</p>
                    <p class="mt-2 text-3xl font-headline font-bold text-amber-700">{{ $unverifiedUsers }}</p>
                </section>
                <section class="card p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Administrator accounts</p>
                    <p class="mt-2 text-3xl font-headline font-bold text-primary">{{ $adminCount }}</p>
                </section>
            </div>

            <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-3">
                <label for="user-search" class="sr-only">Search users</label>
                <input id="user-search" type="search" name="q" value="{{ $search }}"
                       placeholder="Search by name or email..."
                       class="min-w-0 flex-1 rounded-xl border-surface-high bg-white text-ink placeholder:text-ink-faint shadow-sm focus:border-accent focus:ring-accent">
                <button type="submit" class="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-container">Search</button>
                @if ($search !== '')
                    <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-surface-high bg-white px-4 py-2 text-sm font-semibold text-primary-container hover:bg-surface-low">Clear</a>
                @endif
            </form>

            <div class="flex flex-col gap-4 pb-8">
                @forelse ($users as $user)
                    <article class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate font-headline text-base font-bold text-primary">{{ $user->name }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $user->isAdmin() ? 'bg-primary/10 text-primary' : 'bg-surface text-ink-muted' }}">
                                    {{ $user->isAdmin() ? 'Administrator' : 'Student' }}
                                </span>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $user->hasVerifiedEmail() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $user->hasVerifiedEmail() ? 'Verified' : 'Unverified' }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-sm text-ink-muted">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('admin.users.edit', $user) }}"
                           class="inline-flex shrink-0 justify-center rounded-xl border border-surface-high bg-white px-4 py-2 text-sm font-semibold text-primary-container transition hover:bg-surface-low">
                            Manage account
                        </a>
                    </article>
                @empty
                    <div class="card p-10 text-center text-ink-muted">
                        <x-icon name="users" class="mx-auto h-10 w-10 text-ink-faint"/>
                        <p class="mt-3 text-sm">{{ $search !== '' ? 'No accounts match your search.' : 'No user accounts found.' }}</p>
                    </div>
                @endforelse

                {{ $users->links() }}
            </div>
        </div>
    </x-admin-layout>
</x-app-layout>
