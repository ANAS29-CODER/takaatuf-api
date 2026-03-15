<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use SendGrid\Mail\Mail;

class VerificationController extends Controller
{
    //


 public function verify(Request $request)
{
    $token = $request->query('token');

    $user = User::where('email_verification_token', $token)->first();

    if (!$user) {
        return redirect(config('app.frontend_url') . '/verify-error?token=' . $token);
    }

    if (!$user->email_verified_at) {
        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();
    }

    return redirect(config('app.frontend_url') . '/email-verified?token=' . $token);
}

public function resend(Request $request)
{
    $user = $request->user();

    if ($user->email_verified_at) {
        return response()->json([
            'status' => 'error',
            'message' => 'Email already verified'
        ], 400);
    }

    $verificationToken = sha1($user->email . time());

    $user->email_verification_token = $verificationToken;
    $user->save();

    $verificationUrl = config('app.url') . '/api/verify-email?token=' . $verificationToken;

    $email = new \SendGrid\Mail\Mail();
    $email->setFrom("no-reply@takaatuf.org", "Takaatuf");
    $email->setSubject("Verify your email");
    $email->addTo($user->email, $user->full_name);

    $email->addContent(
        "text/html",
        "<p>Click the link to verify your email:</p>
        <a href='{$verificationUrl}'>Verify Email</a>"
    );

    $sendgrid = new \SendGrid(env('SENDGRID_API_KEY'));
    $sendgrid->send($email);

    return response()->json([
        'status' => 'success',
        'message' => 'Verification email resent'
    ]);
}
}
