<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OAuthLoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmail;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //

    public function __construct(
        protected AuthService $authService
    ) {}

    public function redirect(Request $request, string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        $returnUrl = $request->query('returnUrl', '/');
        if (!str_starts_with($returnUrl, '/')) {
            $returnUrl = '/';
        }
        session(['oauth_return_url' => $returnUrl]);

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver->scopes(['email', 'profile']);
        } else {
            $driver->scopes(['email', 'public_profile']); // أو ['public_profile','email']
        }

        return $driver->redirect();
    }


    public function callback(Request $request, string $provider)
    {
        // 1) Provider validation
        $provider = strtolower($provider);
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        // 2) User cancelled
        if ($request->get('error') === 'access_denied') {
            return response()->json([
                'message' => 'Login cancelled. Please try again.'
            ], 200);
        }

        try {
            // 3) Use session-based flow (NO stateless) لأنك بتستخدمي session للـreturnUrl
            $driver = Socialite::driver($provider);

            // Facebook يحتاج fields عشان يرجّع email/name صح
            if ($provider === 'facebook') {
                $driver->fields(['id', 'name', 'email']);
            }

            $socialUser = $driver->user();
            if ($provider === 'facebook') {
                $driver->fields(['id', 'name', 'email']);
            }


            // 5) Login/Create user + token
            $result = $this->authService->oauthLogin($provider, $socialUser);

            // 6) Intended destination
            $returnUrl = session('oauth_return_url', '/');

            return response()->json([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'profile_completed' => $result['profile_completed'],
                'returnUrl' => $returnUrl,
                'message' => $result['profile_completed']
                    ? 'Login successful'
                    : 'Please complete your profile before proceeding',
            ]);
        } catch (\Throwable $e) {
            report($e);


            return response()->json([
                'message' => 'OAuth login failed. Please try again.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * تسجيل مستخدم جديد
     *
     * @param \App\Http\Requests\RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'full_name' => $request->full_name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Send verification email
        $user->sendEmailVerificationNotification();

        // Create token
        $token = $user->createToken('Takaatuf App')->plainTextToken;

        return response()->json([
            'user'    => $user,
            'token'   => $token,
            'message' => 'Registration successful, please verify your email.',
        ], 201);
    }

    // Login عادي
    public function login(LoginRequest $request)
    {
        try {
            $data = $this->authService->loginWithEmail(
                $request->email,
                $request->password
            );

            return response()->json([
                'user' => new UserResource($data['user']),
                'token' => $data['token'],
                'status' => $data['status'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    // Logout
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out successfully']);
    }
}
