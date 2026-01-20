<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class VerificationController extends Controller
{
    //

  public function resend(Request $request)
{
    if ($request->user()->hasVerifiedEmail()) {
        return response()->json([
            'message' => 'Email already verified'
        ], 400);
    }

    $request->user()->sendEmailVerificationNotification();

    return response()->json([
        'message' => 'Verification email resent'
    ]);
}

}
