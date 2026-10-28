<?php

use App\Models\AssessmentResult;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function userManagementAdmin(): User
{
    return User::factory()->admin()->create(['email_verified_at' => now()]);
}

function userManagementStudent(): User
{
    return User::factory()->create(['email_verified_at' => now(), 'user_type' => 'user']);
}

it('restricts user account management to administrators', function () {
    $student = userManagementStudent();

    $this->actingAs($student)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('lets admins search accounts and view user management actions', function () {
    $admin = userManagementAdmin();
    $student = User::factory()->unverified()->create(['user_type' => 'user']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => $student->email]))
        ->assertOk()
        ->assertSee($student->email)
        ->assertSee('User Accounts');

    $this->get(route('admin.users.edit', $student))
        ->assertOk()
        ->assertSee('Profile information')
        ->assertSee('Send password reset link')
        ->assertSee('Mark email as verified')
        ->assertSee('Permanently delete account');
});

it('lets admins update a user profile and invalidates verification after an email change', function () {
    $admin = userManagementAdmin();
    $student = userManagementStudent();
    $student->forceFill([
        'first_name' => 'Sam',
        'last_name' => 'Student',
        'birthday' => '2005-04-03',
        'gender' => 'female',
        'email_verification_code_hash' => 'old-hash',
        'email_verification_code_expires_at' => now()->addMinutes(10),
        'email_verification_code_attempts' => 2,
    ])->save();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $student), [
            'first_name' => 'Alex',
            'last_name' => 'Learner',
            'birthday' => '2006-05-04',
            'gender' => 'others',
            'email' => 'alex@example.com',
        ])
        ->assertRedirect(route('admin.users.edit', $student))
        ->assertSessionHas('status', 'Profile updated. The new email address must be verified.');

    $student->refresh();
    expect($student->name)->toBe('Alex Learner')
        ->and($student->first_name)->toBe('Alex')
        ->and($student->last_name)->toBe('Learner')
        ->and($student->birthday->toDateString())->toBe('2006-05-04')
        ->and($student->gender)->toBe('others')
        ->and($student->email)->toBe('alex@example.com')
        ->and($student->email_verified_at)->toBeNull()
        ->and($student->email_verification_code_hash)->toBeNull()
        ->and($student->email_verification_code_expires_at)->toBeNull()
        ->and($student->email_verification_code_attempts)->toBe(0);
});

it('sends a password reset link without exposing or changing the password', function () {
    Notification::fake();
    $admin = userManagementAdmin();
    $student = userManagementStudent();
    $passwordHash = $student->password;

    $this->actingAs($admin)
        ->post(route('admin.users.password-reset', $student))
        ->assertRedirect()
        ->assertSessionHas('status', 'A password reset link was sent to '.$student->email.'.');

    Notification::assertSentTo($student, ResetPassword::class);
    expect($student->fresh()->password)->toBe($passwordHash);
});

it('lets admins mark a user verified and clears outstanding verification codes', function () {
    $admin = userManagementAdmin();
    $student = User::factory()->unverified()->create([
        'email_verification_code_hash' => 'old-hash',
        'email_verification_code_expires_at' => now()->addMinutes(10),
        'email_verification_code_attempts' => 2,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.users.verify-email', $student))
        ->assertRedirect()
        ->assertSessionHas('status', 'Email address marked as verified.');

    $student->refresh();
    expect($student->hasVerifiedEmail())->toBeTrue()
        ->and($student->email_verification_code_hash)->toBeNull()
        ->and($student->email_verification_code_expires_at)->toBeNull()
        ->and($student->email_verification_code_attempts)->toBe(0);
});

it('deletes a user account and its associated records and quiz source materials', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $admin = userManagementAdmin();
    $student = userManagementStudent();
    $sourcePath = 'uploads/student-material.txt';
    Storage::disk('local')->put($sourcePath, 'Private learning material.');

    $quiz = Quiz::query()->create([
        'user_id' => $student->id,
        'title' => 'Student quiz',
        'source_file' => $sourcePath,
        'status' => Quiz::STATUS_READY,
    ]);
    $student->assessmentResults()->create([
        'program_code' => 'BSCS',
        'program_name' => 'Computer Science',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $student), [
            'delete_confirmation' => $student->email,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect(User::query()->find($student->id))->toBeNull()
        ->and(Quiz::query()->find($quiz->id))->toBeNull()
        ->and(AssessmentResult::query()->where('user_id', $student->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($sourcePath);
});

it('requires confirmation and prevents admins from deleting themselves', function () {
    $admin = userManagementAdmin();
    $student = userManagementStudent();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $student), [
            'delete_confirmation' => 'wrong@example.com',
        ])
        ->assertSessionHasErrors('delete_confirmation');

    $this->delete(route('admin.users.destroy', $admin), [
        'delete_confirmation' => $admin->email,
    ])->assertForbidden();

    expect(User::query()->find($admin->id))->not->toBeNull()
        ->and(User::query()->find($student->id))->not->toBeNull();
});
