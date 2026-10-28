<?php

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Support\Facades\Notification;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)
        ->assertSee('name="first_name"', false)
        ->assertSee('name="last_name"', false)
        ->assertSee('name="birthday"', false)
        ->assertSee('name="gender"', false)
        ->assertSee('value="male"', false)
        ->assertSee('value="female"', false)
        ->assertSee('value="others"', false)
        ->assertSee('Use at least 8 characters')
        ->assertSee('Show password')
        ->assertSee('aria-controls="password_confirmation"', false);
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'birthday' => today()->subYears(13)->toDateString(),
        'gender' => 'female',
        'email' => 'test@example.com',
        'password' => 'Abcdef1!',
        'password_confirmation' => 'Abcdef1!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice'));
    $user = User::where('email', 'test@example.com')->firstOrFail();
    $this->assertSame('Test User', $user->name);
    $this->assertSame('Test', $user->first_name);
    $this->assertSame('User', $user->last_name);
    $this->assertSame('female', $user->gender);
    $this->assertSame(today()->subYears(13)->toDateString(), $user->birthday->toDateString());
    Notification::assertSentTo($user, EmailVerificationCode::class);
});

test('registration rejects users younger than 13', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'birthday' => today()->subYears(13)->addDay()->toDateString(),
        'gender' => 'male',
        'email' => 'underage@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertSessionHasErrors('birthday');

    $this->assertDatabaseMissing('users', ['email' => 'underage@example.com']);
});

test('registration only accepts the supported gender choices', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'birthday' => today()->subYears(13)->toDateString(),
        'gender' => 'unspecified',
        'email' => 'invalid-gender@example.com',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertSessionHasErrors('gender');
});

test('registration rejects passwords that do not meet strength requirements', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'birthday' => today()->subYears(13)->toDateString(),
        'gender' => 'male',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('password');
});

test('registration rejects passwords shorter than 8 characters', function () {
    $this->post('/register', [
        'first_name' => 'Test',
        'last_name' => 'User',
        'birthday' => today()->subYears(13)->toDateString(),
        'gender' => 'male',
        'email' => 'short-password@example.com',
        'password' => 'Abcde1!',
        'password_confirmation' => 'Abcde1!',
    ])->assertSessionHasErrors('password');
});
