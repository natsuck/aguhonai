<?php

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('unverified users cannot access authenticated app routes', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('assessment.index'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect(route('verification.notice'));
});

test('email can be verified with the emailed code', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();
    Notification::fake();

    $user->sendEmailVerificationNotification();

    $notification = Notification::sent($user, EmailVerificationCode::class)->first();
    $this->assertNotNull($notification);
    $this->assertMatchesRegularExpression('/^\d{6}$/', $notification->code);
    $this->assertTrue(Hash::check($notification->code, $user->fresh()->email_verification_code_hash));

    $response = $this->actingAs($user)->post(route('verification.verify'), [
        'code' => $notification->code,
    ]);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->email_verification_code_hash)->toBeNull();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('incorrect verification codes are rejected and counted', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code_hash' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ])->save();

    $this->actingAs($user)
        ->post(route('verification.verify'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($user->fresh()->email_verification_code_attempts)->toBe(1);
});

test('expired verification codes are rejected', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code_hash' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->subMinute(),
    ])->save();

    $this->actingAs($user)
        ->post(route('verification.verify'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
