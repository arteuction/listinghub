<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/*
| 3.0A — authentication + authorization shell. The installer lock is created so
| EnsureInstalled lets requests through; roles/permissions are seeded so the
| permission middleware has something to check.
|
| NOTE on CSRF: Laravel's ValidateCsrfToken is bypassed inside the test harness
| (runningUnitTests), so a 419-without-token case cannot be proven here — it is
| proven at HTTP level in the runtime gate (see docs/RUNTIME_VERIFICATION_LOG).
*/

beforeEach(function () {
    @mkdir(storage_path('app'), 0777, true);
    touch(storage_path('app/installed.lock'));
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    @unlink(storage_path('app/installed.lock'));
});

function makeUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'email' => 'admin@example.com',
        'password' => Hash::make('secret-password'),
        'status' => UserStatus::Active,
    ], $attrs));
}

function admin(array $attrs = []): User
{
    $u = makeUser($attrs);
    $u->assignRole('admin');

    return $u;
}

it('1. logs in a valid admin, regenerates the session id, authenticates', function () {
    $user = admin();
    $this->get('/login'); // establish a session
    $before = session()->getId();

    $res = $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);

    $res->assertRedirect(route('admin.dashboard'));
    expect(session()->getId())->not->toBe($before); // session()->regenerate()
    $this->assertAuthenticatedAs($user);
});

it('2. rejects a wrong password with a generic error', function () {
    admin();

    $res = $this->from('/login')->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);

    $res->assertRedirect('/login')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('These credentials do not match our records.');
    $this->assertGuest();
});

it('2b. gives the SAME generic error for an unknown email', function () {
    $res = $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);

    expect(session('errors')->first('email'))->toBe('These credentials do not match our records.');
    $this->assertGuest();
});

it('3. refuses a suspended user with the same generic error', function () {
    admin(['status' => UserStatus::Suspended]);

    $this->from('/login')->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);

    expect(session('errors')->first('email'))->toBe('These credentials do not match our records.');
    $this->assertGuest();
});

it('4. terminates a live session when the user becomes suspended', function () {
    admin();
    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);
    $this->assertAuthenticated();

    User::query()->where('email', 'admin@example.com')->update(['status' => 'suspended']);

    $this->get('/admin')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('5. a successful login itself clears prior failed attempts', function () {
    admin();
    foreach (range(1, 3) as $i) {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
    }
    expect(RateLimiter::attempts('admin@example.com|127.0.0.1'))->toBe(3);

    // No manual clear — the successful login must reset the limiter.
    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
        ->assertRedirect(route('admin.dashboard'));

    expect(RateLimiter::attempts('admin@example.com|127.0.0.1'))->toBe(0);
});

it('5b. blocks after 5 failed attempts', function () {
    admin();
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
    }

    $this->from('/login')->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
    expect(session('errors')->first('email'))->toContain('Too many login attempts');
});

it('6. invalidates the session and rotates the CSRF token on logout', function () {
    admin();
    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password']);
    $this->assertAuthenticated();
    $tokenBefore = session()->token();

    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->token())->not->toBe($tokenBefore); // regenerateToken()
});

it('7. sends a guest to login and honours the intended URL', function () {
    admin();
    $this->get('/admin')->assertRedirect(route('login'));

    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
        ->assertRedirect('/admin');
});

it('8. lets an admin reach the dashboard', function () {
    $this->actingAs(admin())->get('/admin')->assertOk();
});

it('9. returns 403 for a member without the permission', function () {
    $member = makeUser(['email' => 'member@example.com']);
    $member->assignRole('member');

    $this->actingAs($member)->get('/admin')->assertForbidden();
});

it('10. allows a user with a direct manage-settings permission', function () {
    $user = makeUser(['email' => 'staffer@example.com']);
    $user->givePermissionTo('manage settings');

    $this->actingAs($user)->get('/admin')->assertOk();
});

it('11. matches email case-insensitively (portable)', function () {
    admin(['email' => 'Admin@Example.COM']);

    $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated();
});

it('12. does not let an authenticated user switch identity via POST /login', function () {
    $a = admin(['email' => 'a@example.com']);
    admin(['email' => 'b@example.com']);

    $this->actingAs($a)
        ->post('/login', ['email' => 'b@example.com', 'password' => 'secret-password'])
        ->assertRedirect('/'); // `guest` middleware bounces it, no re-auth

    $this->assertAuthenticatedAs($a); // still A
});

it('13. keeps the password out of session and logs', function () {
    admin();
    $logFile = storage_path('logs/authtest.log');
    @unlink($logFile);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $logFile]);

    $this->from('/login')->post('/login', ['email' => 'admin@example.com', 'password' => 'secret-XYZ-typo']);

    expect(session('_old_input.password') ?? null)->toBeNull();
    if (is_file($logFile)) {
        expect(file_get_contents($logFile))->not->toContain('secret-XYZ-typo');
    }
    @unlink($logFile);
});
