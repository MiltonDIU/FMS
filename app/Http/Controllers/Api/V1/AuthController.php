<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in for the mobile directory app.
 *
 * Tokens rather than sessions: the app has no cookie jar, and a token can be
 * revoked for one device without signing the person out everywhere.
 *
 * A teacher whose password is still the one that was emailed to them can sign
 * in — that is how they get their first token — but RequirePasswordChangeApi
 * then refuses every other endpoint until they replace it.
 */
class AuthController extends Controller
{
    /** One token per device, so signing out of a phone leaves a tablet alone. */
    public const TOKEN_NAME = 'mobile';

    /**
     * The guard named on the auth events this controller fires.
     *
     * Nothing guards these routes with it — the token is checked by Sanctum —
     * but naming it means an entry in the activity log says where the sign-in
     * came from, and an API sign-in can be told apart from a panel one.
     */
    protected const GUARD = 'api';

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['sometimes', 'string', 'max:60'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            /*
             * The same event the panel's login fires, so a failed attempt
             * through the app appears in the activity log beside a failed
             * attempt at the panel. Only the email is passed: the listener takes
             * the identifier by name, and the password has no business there.
             */
            event(new Failed(self::GUARD, $user, ['email' => $credentials['email']]));

            /*
             * One message for both "no such account" and "wrong password". The
             * difference would let anyone with the endpoint work out which of
             * two thousand addresses are real, and every one of them is a
             * university mailbox.
             */
            throw ValidationException::withMessages([
                'email' => ['Those details do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            event(new Failed(self::GUARD, $user, ['email' => $credentials['email']]));

            throw ValidationException::withMessages([
                'email' => ['This account is no longer active.'],
            ]);
        }

        $token = $user->createToken($credentials['device'] ?? self::TOKEN_NAME);

        event(new Login(self::GUARD, $user, false));

        return response()->json([
            'token' => $token->plainTextToken,
            'must_change_password' => $user->password_set_at === null,
            'user' => new UserResource($user->load('teacher.department.faculty', 'teacher.designation')),
        ]);
    }

    /** Revokes the token this request arrived with, and no others. */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()?->delete();

        event(new Logout(self::GUARD, $user));

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * Who the stored token belongs to, and whether it still works.
     *
     * The app calls this on every cold start: a token kept on the device may
     * have been revoked, and there is no other way to find out before a real
     * request fails somewhere less convenient.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('teacher.department.faculty', 'teacher.designation');

        return response()->json([
            'must_change_password' => $user->password_set_at === null,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Replaces the password — both the forced first change and any later one.
     *
     * They are the same operation: prove you know the current password, choose a
     * new one. Setting password_set_at is what lifts the block.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['That is not your current password.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_set_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        /*
         * Every other token is revoked. A password is changed either because it
         * was the emailed one, or because the person suspects somebody else has
         * it — in both cases the sessions opened with the old one should end.
         */
        $current = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $current?->id)->delete();

        return response()->json([
            'message' => 'Password changed.',
            'must_change_password' => false,
        ]);
    }

    /**
     * Sends a reset link. The reply does not say whether the address is known,
     * for the same reason login does not.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If that address belongs to an account, a reset link is on its way.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'password_set_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            // A reset is a recovery; anything already signed in was not them.
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset. Sign in with your new password.']);
    }
}
