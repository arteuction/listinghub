<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/*
| 3.4.0 — registration, email verification, password reset and the member
| profile. The installer lock lets requests past EnsureInstalled; roles are
| seeded because registration assigns `member`.
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function member(array $attrs = []): User
{
    $user = User::factory()->create(array_merge([
        'email' => 'member@example.com',
        'password' => Hash::make('correct-horse-99'),
        'status' => UserStatus::Active,
        'email_verified_at' => now(),
    ], $attrs));

    $user->assignRole('member');

    return $user;
}

// ---------------------------------------------------------------- registration

it('registers a member, assigns the role and logs them in', function () {
    Event::fake([Registered::class]);

    $res = $this->post('/register', [
        'name' => 'Иван Петров',
        'email' => 'ivan@example.com',
        'password' => 'correct-horse-99',
        'password_confirmation' => 'correct-horse-99',
    ]);

    $res->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'ivan@example.com')->firstOrFail();

    expect($user->hasRole('member'))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('correct-horse-99', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class);
});

it('stores the email lowercased so a case variant cannot register twice', function () {
    $this->post('/register', [
        'name' => 'A',
        'email' => 'MiXeD@Example.COM',
        'password' => 'correct-horse-99',
        'password_confirmation' => 'correct-horse-99',
    ])->assertRedirect(route('verification.notice'));

    expect(User::query()->where('email', 'mixed@example.com')->exists())->toBeTrue();

    $this->post('/logout');

    $this->from('/register')->post('/register', [
        'name' => 'B',
        'email' => 'mixed@example.com',
        'password' => 'correct-horse-99',
        'password_confirmation' => 'correct-horse-99',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'mixed@example.com')->count())->toBe(1);
});

it('rejects a weak or unconfirmed password', function (array $payload, string $field) {
    $this->from('/register')->post('/register', array_merge([
        'name' => 'A',
        'email' => 'new@example.com',
    ], $payload))->assertSessionHasErrors($field);

    expect(User::query()->where('email', 'new@example.com')->exists())->toBeFalse();
})->with([
    'too short' => [['password' => 'short1', 'password_confirmation' => 'short1'], 'password'],
    'letters only' => [['password' => 'abcdefghijkl', 'password_confirmation' => 'abcdefghijkl'], 'password'],
    'mismatched' => [['password' => 'correct-horse-99', 'password_confirmation' => 'other-horse-99'], 'password'],
]);

it('keeps an authenticated user away from the register form', function () {
    $this->actingAs(member())->get('/register')->assertRedirect();
});

// --------------------------------------------------------------- verification

it('verifies the email through a signed link', function () {
    $user = member(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1((string) $user->getEmailForVerification()),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('profile.edit'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('refuses an unsigned verification link', function () {
    $user = member(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get('/email/verify/'.$user->getKey().'/'.sha1((string) $user->getEmailForVerification()))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('refuses a signed link whose hash belongs to another address', function () {
    $user = member(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification notification', function () {
    Notification::fake();
    $user = member(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verification-notification')->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

// ------------------------------------------------------------- password reset

it('mails a reset link and lets the token set a new password', function () {
    Notification::fake();
    $user = member();

    $this->post('/forgot-password', ['email' => 'member@example.com'])->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'member@example.com',
        'password' => 'brand-new-pass-42',
        'password_confirmation' => 'brand-new-pass-42',
    ])->assertRedirect(route('login'));

    expect(Hash::check('brand-new-pass-42', $user->fresh()->password))->toBeTrue();
});

it('answers identically for an unknown address so the form cannot enumerate accounts', function () {
    Notification::fake();
    $user = member();

    $known = $this->post('/forgot-password', ['email' => 'member@example.com']);
    $knownStatus = $known->getSession()->get('status');

    $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.com']);
    $unknownStatus = $unknown->getSession()->get('status');

    // Same code, same wording — nothing distinguishes a registered address.
    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and($unknownStatus)->toBe($knownStatus)
        ->and($unknownStatus)->not->toBeNull();

    // ...and only the real account was mailed.
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
});

it('rejects a tampered reset token', function () {
    $user = member();

    $this->from('/reset-password/whatever')->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'member@example.com',
        'password' => 'brand-new-pass-42',
        'password_confirmation' => 'brand-new-pass-42',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('correct-horse-99', $user->fresh()->password))->toBeTrue();
});

it('rotates remember_token on reset so other devices lose their cookie', function () {
    $user = member(['remember_token' => 'old-remember-token']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'member@example.com',
        'password' => 'brand-new-pass-42',
        'password_confirmation' => 'brand-new-pass-42',
    ])->assertRedirect(route('login'));

    expect($user->fresh()->remember_token)->not->toBe('old-remember-token');
});

// -------------------------------------------------------------------- profile

it('requires authentication for the profile', function () {
    $this->get('/profile')->assertRedirect(route('login'));
});

it('updates the profile', function () {
    $user = member();

    $this->actingAs($user)->put('/profile', [
        'name' => 'Ново име',
        'email' => 'member@example.com',
        'about' => 'Кратко описание.',
    ])->assertRedirect();

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Ново име')->and($fresh->about)->toBe('Кратко описание.');
});

it('clears verification and re-sends when the email changes', function () {
    Notification::fake();
    $user = member();

    $this->actingAs($user)->put('/profile', [
        'name' => $user->name,
        'email' => 'changed@example.com',
        'about' => null,
    ])->assertRedirect(route('verification.notice'));

    $fresh = $user->fresh();

    expect($fresh->email)->toBe('changed@example.com')
        ->and($fresh->email_verified_at)->toBeNull();

    Notification::assertSentTo($fresh, VerifyEmail::class);
});

it('refuses an email already taken by another member', function () {
    member();
    $other = User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(User::query()->where('email', 'member@example.com')->firstOrFail())
        ->from('/profile')
        ->put('/profile', ['name' => 'X', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');

    expect($other->fresh()->email)->toBe('taken@example.com');
});

it('changes the password only with the current one', function () {
    $user = member();

    $this->actingAs($user)->from('/profile')->put('/profile/password', [
        'current_password' => 'wrong-password-99',
        'password' => 'brand-new-pass-42',
        'password_confirmation' => 'brand-new-pass-42',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('correct-horse-99', $user->fresh()->password))->toBeTrue();

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'correct-horse-99',
        'password' => 'brand-new-pass-42',
        'password_confirmation' => 'brand-new-pass-42',
    ])->assertRedirect();

    expect(Hash::check('brand-new-pass-42', $user->fresh()->password))->toBeTrue();
});

it('does not link a member to the admin panel', function () {
    $this->actingAs(member())->get('/')->assertOk()->assertDontSee(route('admin.dashboard'), escape: false);
});
