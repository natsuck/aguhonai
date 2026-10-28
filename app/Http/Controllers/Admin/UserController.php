<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'totalUsers' => User::query()->count(),
            'unverifiedUsers' => User::query()->whereNull('email_verified_at')->count(),
            'adminCount' => User::query()->where('user_type', 'admin')->count(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:125'],
            'last_name' => ['nullable', 'string', 'max:125'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now()->subYears(13)->toDateString()],
            'gender' => ['nullable', Rule::in(['male', 'female', 'others'])],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ], [
            'birthday.before_or_equal' => 'The user must be at least 13 years old.',
        ]);

        $firstName = trim((string) ($validated['first_name'] ?? ''));
        $lastName = trim((string) ($validated['last_name'] ?? ''));

        if ($firstName === '' && $lastName === '') {
            return back()
                ->withInput()
                ->withErrors(['first_name' => 'Enter at least a first name or last name.']);
        }

        $validated['first_name'] = $firstName !== '' ? $firstName : null;
        $validated['last_name'] = $lastName !== '' ? $lastName : null;
        $emailChanged = $user->email !== $validated['email'];

        $user->fill([
            ...$validated,
            'name' => trim($firstName.' '.$lastName),
        ]);

        if ($emailChanged) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->email_verified_at = null;
            $user->email_verification_code_hash = null;
            $user->email_verification_code_expires_at = null;
            $user->email_verification_code_attempts = 0;
        }

        $user->save();

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', $emailChanged
                ? 'Profile updated. The new email address must be verified.'
                : 'Profile updated successfully.');
    }

    public function sendPasswordResetLink(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['password' => __($status)]);
        }

        return back()->with('status', 'A password reset link was sent to '.$user->email.'.');
    }

    public function markEmailVerified(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_code_expires_at' => null,
            'email_verification_code_attempts' => 0,
        ])->save();

        return back()->with('status', 'Email address marked as verified.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot delete your own administrator account here.');

        $request->validate([
            'delete_confirmation' => ['required', Rule::in([$user->email])],
        ]);

        $deleted = DB::transaction(function () use ($user): bool {
            $admins = User::query()
                ->where('user_type', 'admin')
                ->lockForUpdate()
                ->get(['id']);

            if ($user->isAdmin() && $admins->count() <= 1) {
                return false;
            }

            $quizzes = $user->quizzes()->get(['id', 'source_file']);
            $disk = Storage::disk(config('filesystems.default'));

            foreach ($quizzes as $quiz) {
                if ($disk->exists($quiz->source_file) && ! $disk->delete($quiz->source_file)) {
                    throw new RuntimeException('An uploaded quiz source file could not be deleted; the account was not removed.');
                }

                foreach (['jobs', 'failed_jobs'] as $table) {
                    DB::table($table)
                        ->where('queue', 'ai')
                        ->where('payload', 'like', '%"quizId";i:'.$quiz->id.';%')
                        ->delete();
                }
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();

            return true;
        });

        if (! $deleted) {
            return back()->withErrors([
                'account' => 'The last administrator account cannot be deleted.',
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'The user account and associated records were permanently deleted.');
    }
}
