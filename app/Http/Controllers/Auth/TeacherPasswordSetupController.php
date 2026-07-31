<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Where a teacher chooses their own password after activating.
 *
 * Deliberately outside the Filament panel: the account is signed in but is held
 * here by RequirePasswordSetup, and a plain page keeps that state obvious rather
 * than dropping someone into a dashboard they are not yet meant to use.
 */
class TeacherPasswordSetupController extends Controller
{
    public function create(Request $request): View | RedirectResponse
    {
        if ($request->user()?->password_set_at !== null) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        return view('auth.set-password', [
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password_set_at !== null) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers()->uncompromised(),
            ],
        ], [
            'password.uncompromised' => 'That password has appeared in a public data breach. Please choose another.',
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'password_set_at' => Carbon::now(),
        ])->save();

        // A new session id now that the credentials have changed, so anything
        // that observed the pre-password session cannot ride it afterwards.
        $request->session()->regenerate();

        return redirect()
            ->route('filament.admin.pages.dashboard')
            ->with('status', 'Your password has been set. Please review your profile.');
    }
}
