<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Account pages.
 *  - Every signed-in user can see their own profile and change their password
 *    (current password required; stored through the "hashed" cast).
 *  - Only the administrator can see the read-only account directory.
 *  - Roles are NOT editable here: the admin role is assigned only through
 *    trusted setup (seeder), as required in Laboratory 3.
 */
class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return view('account.show', [
            'user' => $user,
            'requestCount' => $user->serviceRequests()->count(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
        ])->save();

        // Keep this session, rotate its ID after a credential change.
        $request->session()->regenerate();

        return redirect()->route('account.show')->with('success', 'Your password has been updated.');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $accounts = User::query()
            ->withCount('serviceRequests')
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->paginate(20);

        return view('accounts.index', compact('accounts'));
    }
}
