<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\TeacherActivationService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Redeems an activation link and signs the teacher in.
 *
 * This is the only route in the application that grants a session without a
 * password, so it is deliberately narrow: one token, spent on sight, and only
 * for an account that would be allowed to sign in anyway.
 */
class TeacherActivationController extends Controller
{
    public function __invoke(Request $request, string $token, TeacherActivationService $activation): RedirectResponse
    {
        $teacher = $activation->resolveToken($token);

        // One message for unknown, spent and expired alike. Distinguishing them
        // would confirm to a stranger that a token once existed.
        if (! $teacher || ! $teacher->user) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => 'This activation link is no longer valid. Please ask the administration for a new one.']);
        }

        $user = $teacher->user;

        // The link must not become a way around the panel's own access rules —
        // an archived teacher, a disabled account or an employment status that
        // forbids login is still refused here.
        if (! $user->canAccessPanel(Filament::getPanel('admin'))) {
            Log::warning('[activation] Valid token for teacher #' . $teacher->id . ' but the account cannot access the panel.');

            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => 'Your account is not active. Please contact the administration.']);
        }

        $activation->redeem($teacher);

        Auth::login($user);

        // A fresh session id, so a token that leaked through a shared link or a
        // referrer header cannot be paired with a session someone else already
        // knows about.
        $request->session()->regenerate();

        Log::info('[activation] Teacher #' . $teacher->id . ' signed in via activation link.');

        // Redirecting rather than rendering drops the token from the address
        // bar, and therefore from history and any onward Referer header. The
        // middleware keeps them on the password page until one is set.
        return redirect()->route('teacher.password.create');
    }
}
