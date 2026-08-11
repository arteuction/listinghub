<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Admin user management: search, edit profile fields, set status, assign roles.
 *
 * Deliberately NOT here: setting or resetting another user's password. An
 * admin who can silently take over an account leaves no evidence for the
 * account holder; password changes go through the user's own reset flow.
 *
 * Two self-lockout guards, because an admin editing their own row is the
 * common case and both mistakes are unrecoverable without DB access:
 * an admin cannot suspend themselves, nor drop their own admin role.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($term !== '', function ($query) use ($term): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
                $query->where(function ($inner) use ($escaped): void {
                    $inner->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
                        ->orWhereRaw("email LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
                });
            })
            ->with('roles')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'term' => $term]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $isSelf = $actor instanceof User && (int) $actor->getKey() === (int) $user->getKey();

        if ($isSelf && $data['status'] !== UserStatus::Active->value) {
            return back()->withInput()->withErrors(['status' => 'Не можете да спрете собствения си акаунт.']);
        }

        $roles = array_values(array_filter((array) ($data['roles'] ?? [])));

        if ($isSelf && $user->hasRole('admin') && ! in_array('admin', $roles, true)) {
            return back()->withInput()->withErrors(['roles' => 'Не можете да премахнете собствената си администраторска роля.']);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ]);

        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('status', 'Потребителят е обновен.');
    }
}
