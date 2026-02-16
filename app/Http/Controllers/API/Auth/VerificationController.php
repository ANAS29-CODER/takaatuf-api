<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class VerificationController extends Controller
{
    //

    public function verify(Request $request, $id, $hash)
    {
        if (! $request->hasValidSignature()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Invalid or expired verification link.'], 400)
                : redirect(config('app.frontend_url') . '/email-verification-error');
        }

        $user = User::find($id);
        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'User not found.'], 404)
                : redirect(config('app.frontend_url') . '/404');
        }

        if (! hash_equals(
            sha1($user->getEmailForVerification()),
            $hash
        )) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Invalid verification hash.'], 400)
                : redirect(config('app.frontend_url') . '/email-verification-error');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                // 'email' => $user->email,
                'email_verified' => true,
                // 'avatar' => $user->avatar,
                // 'role' => $user->role,
                'profile_completed' => (bool) $user->profile_completed,
            ],

        ]);
        // return $request->expectsJson()
        //     ? response()->json(['message' => 'Email verified successfully.'])
        //     : redirect(config('app.frontend_url') . '/email-verified');
    }

    public function resend(Request $request)
    {
        $user = $request->user(); // auth user

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email resent'
        ], 200);
    }
}
