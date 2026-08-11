<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\PasswordUpdateRequest;
use App\Http\Requests\Member\ProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The member's own account page. Everything here is scoped to the authenticated
 * user — there is no id in any route, so one member cannot address another's
 * profile at all.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('member.profile', ['user' => $this->currentUser($request)]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        $email = (string) $request->validated('email');

        // Changing the address invalidates the previous confirmation: the new
        // one has not been proven to belong to this person yet.
        $emailChanged = $email !== $user->email;

        $user->fill([
            'name' => (string) $request->validated('name'),
            'email' => $email,
            'about' => $request->validated('about'),
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')
                ->with('status', 'Профилът е обновен. Потвърдете новия имейл адрес.');
        }

        return back()->with('status', 'Профилът е обновен.');
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $this->currentUser($request);

        // Rotating remember_token invalidates every "remember me" cookie issued
        // to other devices. Active server-side sessions elsewhere are NOT ended:
        // that needs Illuminate\Session\Middleware\AuthenticateSession, which
        // this application does not register — so the message does not claim it.
        $user->forceFill([
            'password' => Hash::make((string) $request->validated('password')),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return back()->with('status', 'Паролата е сменена.');
    }

    /**
     * The routes are behind `auth`, so this only guards against a guard
     * misconfiguration — it never fires in normal operation.
     */
    private function currentUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
