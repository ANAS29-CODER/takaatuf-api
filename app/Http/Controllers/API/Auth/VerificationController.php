<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class VerificationController extends Controller
{
    //

      public function verifyEmail($id, $hash)
    {
        $user = User::findOrFail($id);
        if (Hash::check($hash, $user->getEmailVerificationHash())) {
            $user->markEmailAsVerified();
            return response()->json(['message' => 'Email verified successfully']);
        }

        return response()->json(['message' => 'Invalid verification link'], 400);
    }
}
